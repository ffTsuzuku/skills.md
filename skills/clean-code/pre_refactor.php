<?php

namespace App\Services\Billing;

use App\Models\Bill;
use App\Models\BillCode;
use App\Models\FeatureEnrollment;
use App\Models\Patient;
use App\Models\PatientEnrollment;
use App\Models\RecordedTime;
use App\Models\Task;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Exception;

class BillingTimeProcessor
{
    private const CPT_TYPE_CCM = 'CCM';
    private const CPT_TYPE_RPM = 'RPM';

    private const RPM_2026_BILL_CODE_ID = 99470;
    private const OLIVERA_CCM_BILL_CODE = 99491;

    private const FEATURE_ID_CCM = 1;
    private const FEATURE_ID_RPM = 2;
    private const FEATURE_ID_RPM_ENROLLMENT = 9;

    private const DEFAULT_BILLABLE_MINUTES = [60, 40, 20];
    private const OLIVERA_CCM_BILLABLE_MINUTES = [90, 60, 30];
    private const RPM_2026_BILLABLE_MINUTES = [60, 40, 20, 10];

    public function processTime(RecordedTime $recordedTime): void
    {
        if (! $recordedTime->counted_towards) {
            return;
        }

        $this->loadRelations($recordedTime);

        if (! $this->isValidForProcessing($recordedTime)) {
            return;
        }

        $facilitySettings = $this->getFacilitySettings($recordedTime);
        if (! $facilitySettings['enabled']) {
            return;
        }

        $patientEnrollment = $this->resolvePatientEnrollment($recordedTime, $facilitySettings['enrollmentReqd']);
        if ($facilitySettings['enrollmentReqd'] && ! $patientEnrollment) {
            $this->markAsNotCounted($recordedTime);
            return;
        }

        $billCodes = $this->determineBillCodes($recordedTime);
        if (empty($billCodes)) {
            Log::info("Careflow {$recordedTime->task->careflow->name} ({$recordedTime->task->careflow->id}) does not have bill codes");
            return;
        }

        $chartCreatedAt = $this->determineChartCreatedAt($recordedTime);
        $wasCountedTowards = false;

        foreach ($billCodes as $codeInfo) {
            if ($this->processBillCode($recordedTime, (object) $codeInfo, $chartCreatedAt, $patientEnrollment)) {
                $wasCountedTowards = true;
            }
        }

        if (! $wasCountedTowards) {
            $this->markAsNotCounted($recordedTime);
        }
    }

    private function loadRelations(RecordedTime $recordedTime): void
    {
        $recordedTime->load([
            'patient.facility.corporation',
            'patient.facility.practices',
            'task.careflow',
            'bill.bill_code',
            'taskSession',
        ]);
    }

    private function isValidForProcessing(RecordedTime $recordedTime): bool
    {
        $patient = $recordedTime->patient;
        if (! $patient) {
            Log::error("RecordedTime {$recordedTime->id} has no patient.");
            return false;
        }

        if (! $patient->facility) {
            Log::error("Patient {$patient->id} has no facility. For RecordedTime {$recordedTime->id}");
            return false;
        }

        if (! $recordedTime->practice_id) {
            Log::error("Patient {$patient->id} does not have a primary practice! For RecordedTime {$recordedTime->id}");
            return false;
        }

        return true;
    }

    private function getFacilitySettings(RecordedTime $recordedTime): array
    {
        $facility = $recordedTime->patient->facility;
        return $this->resolveFacilityRequirements($recordedTime->cpt_type, $facility, $recordedTime->practice_id);
    }

    private function resolvePatientEnrollment(RecordedTime $recordedTime, bool $enrollmentReqd): ?PatientEnrollment
    {
        $patient = $recordedTime->patient;
        return $this->getPatientEnrollment(
            $recordedTime->cpt_type,
            $patient,
            $recordedTime->practice_id,
            $enrollmentReqd
        );
    }

    private function markAsNotCounted(RecordedTime $recordedTime): void
    {
        $recordedTime->counted_towards = false;
        $recordedTime->save();
    }

    private function determineChartCreatedAt(RecordedTime $recordedTime): Carbon
    {
        // we use the chart created at date to support adding recorded time for old charts for the addendum feature
        // Some RPM tasks can stay open for months, so we don't use task.created_at_local to determine which bill it gets logged to (no addendums for RPM)
        // RPM time only logs to current RPM Chart Review
        $chartCreatedAtLocal = Carbon::parse($recordedTime->task->created_at_local);
        
        if ($recordedTime->cpt_type !== self::CPT_TYPE_CCM) {
            $chartCreatedAtLocal = Carbon::parse($recordedTime->recorded_at_local);
        }
        
        return $chartCreatedAtLocal;
    }

    private function determineBillCodes(RecordedTime $recordedTime): array
    {
        $facility = $recordedTime->patient->facility;
        $practiceName = $facility->practices->firstWhere('id', $recordedTime->practice_id)->name ?? null;

        if ($recordedTime->cpt_type === self::CPT_TYPE_CCM && $practiceName === 'Olivera Community Care') {
            return [['code' => self::OLIVERA_CCM_BILL_CODE, 'track_time_on_bill' => true]];
        }

        return $recordedTime->task->careflow->bill_codes ?? [];
    }

    private function getBillableTargetMinutes(RecordedTime $recordedTime, Bill $bill): array
    {
        $facility = $recordedTime->patient->facility;
        $practiceName = $facility->practices->firstWhere('id', $recordedTime->practice_id)->name ?? null;

        if ($recordedTime->cpt_type === self::CPT_TYPE_CCM && $practiceName === 'Olivera Community Care') {
            return self::OLIVERA_CCM_BILLABLE_MINUTES;
        }

        if ($bill->bill_code_id == self::RPM_2026_BILL_CODE_ID) {
            return self::RPM_2026_BILLABLE_MINUTES;
        }

        return self::DEFAULT_BILLABLE_MINUTES;
    }

    private function processBillCode(RecordedTime $recordedTime, object $careflowBillCode, Carbon $chartCreatedAt, ?PatientEnrollment $patientEnrollment): bool
    {
        if (empty($careflowBillCode->code) || empty($careflowBillCode->track_time_on_bill)) {
            return false;
        }

        $billCode = BillCode::where('code', $careflowBillCode->code)
            ->with('completesCareflow')
            ->first();

        if (! $billCode) {
            return false;
        }

        $billCodeIds = $this->getApplicableBillCodeIds($billCode->id, $recordedTime, $chartCreatedAt);
        
        $bill = $this->findOrCreateBill($recordedTime, $billCodeIds, $chartCreatedAt, $patientEnrollment);
        $task = $this->findOrCreateTask($recordedTime, $bill, $billCode, $chartCreatedAt);

        $this->linkRecordedTimeToBill($recordedTime, $bill);

        $isBillable = $this->updateBillTimeAndStatus($recordedTime, $bill, $task, $billCode, $patientEnrollment);

        $this->updateTaskStatus($recordedTime, $task, $isBillable, $bill);

        return true;
    }

    private function getApplicableBillCodeIds(int $baseBillCodeId, RecordedTime $recordedTime, Carbon $chartCreatedAt): array
    {
        $billCodeIds = [$baseBillCodeId];
        $is2026OrLater = $chartCreatedAt->year >= 2026;

        if ($is2026OrLater && $recordedTime->cpt_type === self::CPT_TYPE_RPM) {
            $billCodeIds[] = self::RPM_2026_BILL_CODE_ID;
        }

        return $billCodeIds;
    }

    private function findOrCreateBill(RecordedTime $recordedTime, array $billCodeIds, Carbon $chartCreatedAt, ?PatientEnrollment $patientEnrollment): Bill
    {
        $patient = $recordedTime->patient;
        $facility = $patient->facility;
        $timezone = $facility->timezone;

        $startDate = $chartCreatedAt->copy()->startOfMonth();
        $endDate = $chartCreatedAt->copy()->endOfMonth();
        $chartCreatedAtLocalStr = $chartCreatedAt->toDateTimeString();

        $bill = Bill::whereIn('bill_code_id', $billCodeIds)
            ->where('patient_id', $patient->id)
            ->where('facility_id', $facility->id)
            ->where('corporation_id', $facility->corporation_id)
            ->where('practice_id', $recordedTime->practice_id)
            ->where('start_date', '<=', $chartCreatedAtLocalStr)
            ->where('end_date', '>=', $chartCreatedAtLocalStr)
            ->orderBy('id', 'DESC')
            ->first();

        if ($bill) {
            return $bill;
        }

        $nowInTimezone = Carbon::now($timezone);
        $isInPeriod = $nowInTimezone->between($startDate, $endDate);

        $bill = Bill::create([
            'bill_code_id' => end($billCodeIds),
            'patient_id' => $patient->id,
            'facility_id' => $facility->id,
            'corporation_id' => $facility->corporation_id,
            'practice_id' => $recordedTime->practice_id,
            'target' => 'patient',
            'start_date' => $startDate->toDateTimeString(),
            'end_date' => $endDate->toDateTimeString(),
            'in_period' => $isInPeriod ? 1 : 0,
            'sum_seconds' => 0,
            'dx_codes' => $patientEnrollment ? $patientEnrollment->conditions_string : null,
            'patient_enrollment_id' => $patientEnrollment ? $patientEnrollment->id : null,
        ]);

        return $bill;
    }

    private function findOrCreateTask(RecordedTime $recordedTime, Bill $bill, BillCode $billCode, Carbon $chartCreatedAt): Task
    {
        $task = $recordedTime->task;
        $patient = $recordedTime->patient;
        $facility = $patient->facility;

        if ($task && (! $billCode->completesCareflow || $task->careflow_id == $billCode->completesCareflow->id)) {
            return $task;
        }

        $startDateStr = $chartCreatedAt->copy()->startOfMonth()->toDateTimeString();
        $endDateStr = $chartCreatedAt->copy()->endOfMonth()->toDateTimeString();

        $task = Task::where('careflow_id', $billCode->completesCareflow->id)
            ->where('target', 'patient')
            ->where('patient_id', $patient->id)
            ->where('facility_id', $facility->id)
            ->where('corporation_id', $facility->corporation_id)
            ->where('practice_id', $recordedTime->practice_id)
            ->where('created_at_local', '<', $endDateStr)
            ->where('created_at_local', '>', $startDateStr)
            ->orderBy('id', 'DESC')
            ->first();

        if (! $task) {
            $task = Task::create([
                'careflow_id' => $billCode->completesCareflow->id,
                'target' => 'patient',
                'patient_id' => $patient->id,
                'facility_id' => $facility->id,
                'corporation_id' => $facility->corporation_id,
                'practice_id' => $recordedTime->practice_id,
                'bill_id' => $bill->id,
                'status' => 'task_started',
                'created_at_local' => Carbon::now($facility->timezone)->toDateTimeString(),
                'details' => "Monitor patient for {$recordedTime->cpt_type}",
                'priority' => 4,
            ]);
        }

        Log::info("{$recordedTime->task->careflow->name} logging time to {$billCode->code} which logs to {$billCode->completesCareflow->name} task {$task->id}.");

        return $task;
    }

    private function linkRecordedTimeToBill(RecordedTime $recordedTime, Bill $bill): void
    {
        $recordedTime->bill_id = $bill->id;
        $recordedTime->save();
    }

    private function updateBillTimeAndStatus(RecordedTime $recordedTime, Bill $bill, Task $task, BillCode $billCode, ?PatientEnrollment $patientEnrollment): bool
    {
        $secondsPrev = (int) $bill->sum_seconds;
        $bill->increment('sum_seconds', $recordedTime->seconds);
        $bill->refresh();
        $secondsCurr = (int) $bill->sum_seconds;

        $isBillable = (bool) $bill->date_billable;

        if (! $bill->date_submitted) {
            $reachedThreshold = $this->getNewlyReachedThreshold($recordedTime, $bill, $secondsPrev, $secondsCurr);
            
            if ($reachedThreshold !== null) {
                $patient = $recordedTime->patient;
                Log::info("patient {$patient->id} stats: seconds_curr={$secondsCurr}, seconds_prev={$secondsPrev}, billable_seconds=" . ($reachedThreshold * 60));
                Log::info("patient {$patient->id} has reached {$reachedThreshold} billable minutes for {$recordedTime->cpt_type} time");

                $dos = $this->calculateDateOfService($patient, $bill, $task);
                $this->applyBillableState($bill, $recordedTime, $patientEnrollment, $dos);

                if ($bill->bill_code_id == self::RPM_2026_BILL_CODE_ID && $reachedThreshold >= 20) {
                    $bill->bill_code_id = $billCode->id;
                }

                $isBillable = true;
            }
        }

        if ($task->status === 'resolved' && $billCode->cpt_category === self::CPT_TYPE_CCM) {
            $isBillable = $this->applyResolvedCcmStatus($recordedTime, $bill, $task, $patientEnrollment) || $isBillable;
        }

        $bill->save();

        return $isBillable;
    }

    private function getNewlyReachedThreshold(RecordedTime $recordedTime, Bill $bill, int $secondsPrev, int $secondsCurr): ?int
    {
        $billableTargets = $this->getBillableTargetMinutes($recordedTime, $bill);

        foreach ($billableTargets as $billableMinutes) {
            $billableSeconds = $billableMinutes * 60;
            
            if ($secondsCurr >= $billableSeconds && $secondsPrev < $billableSeconds) {
                return $billableMinutes;
            }
        }
        
        return null;
    }

    private function applyResolvedCcmStatus(RecordedTime $recordedTime, Bill $bill, Task $task, ?PatientEnrollment $patientEnrollment): bool
    {
        $patient = $recordedTime->patient;
        $resolvedAt = Carbon::parse($task->resolved_at);
        $dos = $this->calculateDateOfService($patient, $bill, $task, $resolvedAt);

        $this->applyBillableState($bill, $recordedTime, $patientEnrollment, $dos);

        if ($bill->status === 'pending') {
            $bill->status = 'ready';
        }

        return true;
    }

    private function applyBillableState(Bill $bill, RecordedTime $recordedTime, ?PatientEnrollment $patientEnrollment, ?Carbon $dos): void
    {
        $bill->dos = $dos;
        
        if (! $bill->date_billable) {
            $bill->date_billable = $recordedTime->recorded_at_local;
        }
        
        $bill->status = 'ready';
        $bill->dx_codes = $patientEnrollment ? $patientEnrollment->conditions_string : null;
        $bill->patient_enrollment_id = $patientEnrollment ? $patientEnrollment->id : null;
        $bill->user_id = $recordedTime->user_id;

        if (! $bill->task_session_id) {
            $bill->task_session_id = $recordedTime->task_session_id;
        }
    }

    private function calculateDateOfService(Patient $patient, Bill $bill, Task $task, ?Carbon $resolvedAt = null): ?Carbon
    {
        $timezone = $patient->facility->timezone;
        $dos = null;

        if ($task->datetime_of_service) {
            $dos = Carbon::parse($task->datetime_of_service, $timezone);
        }

        $billStartDate = Carbon::parse($bill->start_date, $timezone);
        $billEndDate = Carbon::parse($bill->end_date, $timezone);

        if (! $dos || ! $dos->between($billStartDate, $billEndDate)) {
            $dos = $resolvedAt ? $resolvedAt : Carbon::parse($bill->end_date, $timezone);
        }

        $deceasedDate = $patient->deceased_date ? Carbon::parse($patient->deceased_date, $timezone) : null;
        if ($deceasedDate && $deceasedDate->isBefore($dos) && $deceasedDate->isAfter($billStartDate)) {
            $dos = $deceasedDate;
        }

        return $dos;
    }

    private function updateTaskStatus(RecordedTime $recordedTime, Task $task, bool $isBillable, Bill $bill): void
    {
        if ($task->status !== 'resolved' && $task->status !== 'abandoned') {
            $newStatus = $isBillable ? 'task_finished' : 'task_started';

            try {
                $taskSession = $recordedTime->taskSession;
                if ($taskSession) {
                    if ($task->id !== $recordedTime->task_id) {
                        $newTaskSession = $taskSession->replicate();
                        $newTaskSession->task_id = $task->id;
                        $newTaskSession->old_status = $task->status;
                        $newTaskSession->new_status = $newStatus;
                        $newTaskSession->save();
                    } else {
                        $taskSession->new_status = $newStatus;
                        $taskSession->save();
                    }
                }
            } catch (Exception $e) {
                Log::error("Error saving task session for task {$task->id} and recorded time {$recordedTime->id}: " . $e->getMessage());
            }
            $task->status = $newStatus;
        }

        $task->bill_id = $bill->id;
        $task->save();
    }

    protected function resolveFacilityRequirements($type, $facility, $primaryPracticeId): array
    {
        $ret = [
            'enabled' => false,
            'enrollmentReqd' => false,
        ];

        $featureIds = [];
        if ($type === self::CPT_TYPE_RPM) {
            $featureIds = [self::FEATURE_ID_RPM, self::FEATURE_ID_RPM_ENROLLMENT];
        } elseif ($type === self::CPT_TYPE_CCM) {
            $featureIds = [self::FEATURE_ID_CCM];
            $ret['enrollmentReqd'] = true;
        }

        if (! $type || ! $facility || empty($featureIds)) {
            return $ret;
        }

        $featuresMap = FeatureEnrollment::has_features(
            'facility', $facility->id, $featureIds, $primaryPracticeId
        );

        $facilityFeatures = $featuresMap[$facility->id] ?? [];
        $mainFeature = collect($facilityFeatures)->firstWhere('feature_id', $featureIds[0]);

        if (! $mainFeature || empty($mainFeature['enabled'])) {
            Log::info("{$type} feature not enabled for facility {$facility->name} ({$facility->id})");
            return $ret;
        }

        $ret['enabled'] = true;
        
        if ($type === self::CPT_TYPE_CCM) {
            return $ret;
        }

        $enrollmentFeature = collect($facilityFeatures)->firstWhere('feature_id', self::FEATURE_ID_RPM_ENROLLMENT);
        if ($enrollmentFeature && isset($enrollmentFeature['enabled']) && $enrollmentFeature['enabled'] === false) {
            Log::info("{$type} enrollment is required for facility {$facility->name} ({$facility->id})");
            $ret['enrollmentReqd'] = true;
        }

        return $ret;
    }

    protected function getPatientEnrollment($type, $patient, $primaryPracticeId, $isEnrollmentReqd = false): ?PatientEnrollment
    {
        if (! $type || ! $patient || ! $primaryPracticeId) {
            return null;
        }

        if ($type === self::CPT_TYPE_CCM) {
            $isEnrollmentReqd = true;
        }

        $patientEnrollment = PatientEnrollment::where('patient_id', $patient->id)
            ->where('practice_id', $primaryPracticeId)
            ->whereNull('deleted_at')
            ->where('type', $type)
            ->where(function ($query) {
                $query->whereNull('expiry_date')
                    ->orWhere('expiry_date', '>', Carbon::now());
            })
            ->whereHas('conditions')
            ->with(['conditions' => function ($query) {
                $query->select('icd10');
            }])
            ->orderBy('id', 'DESC')
            ->first();

        if ($patientEnrollment) {
            $patientEnrollment->conditions_string = $patientEnrollment->conditions->pluck('icd10')->implode(',') ?: null;
        } elseif ($isEnrollmentReqd) {
            Log::info("patient {$patient->last_name}, {$patient->first_name} ({$patient->id}) not enrolled in {$type}");
        }

        return $patientEnrollment;
    }
}
