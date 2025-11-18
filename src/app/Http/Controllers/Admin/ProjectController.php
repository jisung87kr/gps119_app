<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProjectController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Project::with('creator')
            ->withCount('requests');

        // 검색
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // 상태 필터
        if ($request->has('status') && $request->status) {
            $query->byStatus($request->status);
        }

        $projects = $query->latest()->paginate(20);

        return view('admin.projects.index', compact('projects'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('admin.projects.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'is_active' => ['boolean'],
        ]);

        $validated['created_by'] = Auth::id();

        $project = Project::create($validated);

        return redirect()->route('admin.projects.show', $project->id)
            ->with('success', '프로젝트가 성공적으로 생성되었습니다.');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $project = Project::with(['creator', 'requests.user', 'requests.assignedRescuer'])
            ->withCount([
                'requests',
                'requests as pending_requests_count' => function($query) {
                    $query->where('status', 'pending');
                },
                'requests as in_progress_requests_count' => function($query) {
                    $query->where('status', 'in_progress');
                },
                'requests as completed_requests_count' => function($query) {
                    $query->where('status', 'completed');
                },
                'requests as cancelled_requests_count' => function($query) {
                    $query->where('status', 'cancelled');
                },
            ])
            ->findOrFail($id);

        // 일별 요청 추이 (프로젝트 기간 동안)
        $dailyStats = \DB::table('requests')
            ->where('project_id', $id)
            ->whereNotNull('created_at')
            ->select(
                \DB::raw('DATE(created_at) as date'),
                \DB::raw('COUNT(*) as count')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // 구조대원별 처리 현황
        $rescuerStats = \DB::table('requests')
            ->where('project_id', $id)
            ->whereNotNull('assigned_rescuer_id')
            ->join('users', 'requests.assigned_rescuer_id', '=', 'users.id')
            ->select(
                'users.id',
                'users.name',
                \DB::raw('COUNT(*) as total_assigned'),
                \DB::raw('SUM(CASE WHEN requests.status = "completed" THEN 1 ELSE 0 END) as completed_count'),
                \DB::raw('SUM(CASE WHEN requests.status = "in_progress" THEN 1 ELSE 0 END) as in_progress_count')
            )
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('total_assigned')
            ->get();

        return view('admin.projects.show', compact('project', 'dailyStats', 'rescuerStats'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $project = Project::with(['creator', 'requests'])
            ->withCount('requests')
            ->findOrFail($id);
        return view('admin.projects.edit', compact('project'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $project = Project::findOrFail($id);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        // 체크박스는 체크되지 않으면 요청에 포함되지 않으므로 명시적으로 처리
        $validated['is_active'] = $request->has('is_active') ? true : false;

        $project->update($validated);

        // 날짜 변경 시 상태 자동 재계산
        $project->updateStatus();

        return redirect()->route('admin.projects.show', $project->id)
            ->with('success', '프로젝트가 성공적으로 수정되었습니다.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $project = Project::findOrFail($id);
        $project->delete();

        return redirect()->route('admin.projects.index')
            ->with('success', '프로젝트가 성공적으로 삭제되었습니다.');
    }

    /**
     * Generate QR code for project URL
     */
    public function qrcode(string $id)
    {
        $project = Project::findOrFail($id);

        // slug가 없으면 생성
        if (!$project->slug) {
            $project->slug = \Illuminate\Support\Str::slug($project->name);

            // 한글 등으로 slug가 빈 문자열이 된 경우 랜덤 문자열 생성
            if (empty($project->slug)) {
                $project->slug = 'project-' . \Illuminate\Support\Str::random(8);
            }

            $originalSlug = $project->slug;
            $count = 1;
            while (Project::where('slug', $project->slug)->where('id', '!=', $project->id)->exists()) {
                $project->slug = $originalSlug . '-' . $count;
                $count++;
            }
            $project->save();
        }

        $url = $project->getUrl();

        // QR 코드 생성 (endroid/qr-code 6.x)
        $qrCode = new \Endroid\QrCode\QrCode(
            data: $url,
            encoding: new \Endroid\QrCode\Encoding\Encoding('UTF-8'),
            size: 300,
            margin: 10,
        );

        $writer = new \Endroid\QrCode\Writer\PngWriter();
        $result = $writer->write($qrCode);

        return response($result->getString())
            ->header('Content-Type', 'image/png');
    }

    /**
     * Clone a project
     */
    public function clone(string $id)
    {
        $originalProject = Project::findOrFail($id);

        // 새 프로젝트 생성 (요청은 복제하지 않음)
        $newProject = $originalProject->replicate();
        $newProject->name = $originalProject->name . ' (복제본)';
        $newProject->slug = null; // slug는 자동 생성되도록
        $newProject->created_by = Auth::id();

        // 날짜를 현재 날짜 기준으로 조정
        $duration = $originalProject->start_date->diffInDays($originalProject->end_date);
        $newProject->start_date = now();
        $newProject->end_date = now()->addDays($duration);

        $newProject->save();

        return redirect()->route('admin.projects.edit', $newProject->id)
            ->with('success', '프로젝트가 성공적으로 복제되었습니다. 필요한 정보를 수정하세요.');
    }

    /**
     * Export project requests to CSV
     */
    public function exportCsv(string $id)
    {
        $project = Project::with(['requests.user', 'requests.assignedRescuer'])
            ->findOrFail($id);

        $filename = sprintf('%s_%s.csv',
            \Illuminate\Support\Str::slug($project->name),
            now()->format('Y-m-d')
        );

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function() use ($project) {
            $file = fopen('php://output', 'w');

            // UTF-8 BOM 추가 (엑셀에서 한글 깨짐 방지)
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            // 헤더 행
            fputcsv($file, [
                'ID',
                '요청자',
                '연락처',
                '주소',
                '위도',
                '경도',
                '설명',
                '우선순위',
                '상태',
                '담당자',
                '요청일시',
                '수정일시'
            ]);

            // 데이터 행
            foreach ($project->requests as $request) {
                fputcsv($file, [
                    $request->id,
                    $request->user->name ?? '',
                    $request->user->phone ?? '',
                    $request->address ?? '',
                    $request->latitude,
                    $request->longitude,
                    $request->description ?? '',
                    $request->priority?->value ?? '',
                    $request->status?->value ?? '',
                    $request->assignedRescuer->name ?? '',
                    $request->created_at->format('Y-m-d H:i:s'),
                    $request->updated_at->format('Y-m-d H:i:s'),
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
