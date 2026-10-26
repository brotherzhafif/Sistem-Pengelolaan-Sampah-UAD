<?php

namespace App\Http\Controllers;

use App\Livewire\Pages\Kap\Index as KapIndexComponent;
use App\Livewire\Pages\Kap\Survey as KapSurveyComponent;
use App\Models\KapSurvey;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class KapController extends Controller
{
    /**
     * Menampilkan dashboard analitik Survei KAP internal.
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        if (!$user || (!$user->hasRole(['super_admin', 'Super Admin', 'admin_kampus', 'Admin Kampus', 'koordinator_tps3r', 'Koordinator TPS3R', 'viewer', 'Viewer', 'auditor_pimpinan', 'Auditor / Pimpinan']) && !$user->can('kap.view'))) {
            abort(403, 'Akses ditolak: Anda tidak memiliki wewenang untuk mengakses modul Analitik Survei KAP.');
        }

        return app(KapIndexComponent::class)();
    }

    /**
     * Menampilkan formulir publik pengisian Survei KAP.
     */
    public function survey(Request $request)
    {
        return app(KapSurveyComponent::class)();
    }

    /**
     * Memproses dan menyimpan entri hasil pengisian Survei KAP.
     */
    public function submitSurvey(array $data): array
    {
        Validator::make($data, [
            'campus_id' => ['required', 'exists:campuses,id'],
            'respondent_role' => ['required', 'in:mahasiswa,dosen,tendik,outsourcing'],
            'faculty_unit' => ['required', 'string', 'max:100'],
            'gender' => ['required', 'in:Laki-laki,Perempuan'],
            'has_attended_training' => ['required', 'boolean'],
            'is_willing_volunteer' => ['required', 'boolean'],
            'respondent_name' => ['nullable', 'string', 'max:100'],
            'respondent_identifier' => ['nullable', 'string', 'max:50'],
            'residence_type' => ['nullable', 'string', 'max:50'],
            'device_id' => ['nullable', 'string', 'max:64'],
            'knowledge' => ['required', 'array'],
            'attitude' => ['required', 'array'],
            'practice' => ['required', 'array'],
            'satisfaction' => ['required', 'array'],
            'facilities' => ['nullable', 'array'],
            'barriers' => ['nullable', 'array'],
            'motivations' => ['nullable', 'array'],
            'feedback' => ['nullable', 'string', 'max:1000'],
        ], [
            'campus_id.required' => 'Pilih unit kampus tempat Anda beraktivitas.',
            'faculty_unit.required' => 'Pilih fakultas atau unit kerja Anda.',
        ])->validate();

        $deviceId = !empty($data['device_id']) ? strip_tags(trim($data['device_id'])) : null;
        if ($deviceId && KapSurvey::hasDeviceSubmittedToday($deviceId)) {
            return [
                'success' => false,
                'message' => 'Perangkat ini sudah digunakan untuk mengisi survei hari ini. Batas pengisian adalah 1 kali per perangkat per hari.',
            ];
        }

        $scores = KapSurvey::calculateScores(
            $data['knowledge'] ?? [],
            $data['attitude'] ?? [],
            $data['practice'] ?? [],
            $data['satisfaction'] ?? []
        );

        $survey = KapSurvey::create([
            'campus_id' => (int) $data['campus_id'],
            'respondent_name' => !empty($data['respondent_name']) ? strip_tags(trim($data['respondent_name'])) : null,
            'respondent_identifier' => !empty($data['respondent_identifier']) ? strip_tags(trim($data['respondent_identifier'])) : null,
            'respondent_role' => $data['respondent_role'],
            'faculty_unit' => strip_tags(trim($data['faculty_unit'])),
            'gender' => $data['gender'],
            'has_attended_training' => (bool) $data['has_attended_training'],
            'is_willing_volunteer' => (bool) $data['is_willing_volunteer'],
            'residence_type' => !empty($data['residence_type']) ? $data['residence_type'] : 'kos',
            'knowledge_responses' => $data['knowledge'] ?? [],
            'attitude_responses' => array_values($scores['attitude_int']),
            'practice_responses' => array_values($scores['practice_int']),
            'satisfaction_responses' => array_values($scores['satisfaction_int']),
            'facility_responses' => !empty($data['facilities']) ? array_values($data['facilities']) : [],
            'barrier_responses' => [
                'barriers' => !empty($data['barriers']) ? array_values($data['barriers']) : [],
                'motivations' => !empty($data['motivations']) ? array_values($data['motivations']) : [],
            ],
            'knowledge_score' => $scores['knowledge_score'],
            'attitude_score' => $scores['attitude_score'],
            'practice_score' => $scores['practice_score'],
            'satisfaction_score' => $scores['satisfaction_score'],
            'overall_score' => $scores['overall_score'],
            'category' => $scores['category'],
            'feedback' => !empty($data['feedback']) ? strip_tags(trim($data['feedback'])) : null,
            'survey_date' => Carbon::today()->format('Y-m-d'),
            'device_id' => $deviceId,
            'ip_address' => !empty($data['ip_address']) ? $data['ip_address'] : request()->ip(),
            'user_agent' => !empty($data['user_agent']) ? substr($data['user_agent'], 0, 500) : substr(request()->userAgent() ?? '', 0, 500),
        ]);

        return [
            'success' => true,
            'survey' => $survey,
            'message' => 'Survei KAP Anda berhasil dikirim! Terima kasih atas partisipasi Anda.',
        ];
    }
}
