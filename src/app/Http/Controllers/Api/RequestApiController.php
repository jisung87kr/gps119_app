<?php


namespace App\Http\Controllers\Api;

use App\Enums\RequestPriority;
use App\Enums\RequestStatus;
use App\Http\Controllers\Controller;
use App\Models\Request as RequestModel;
use App\Models\User;
use App\Services\RequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\Rule;

class RequestApiController extends Controller
{
    public function __construct(
        private RequestService $requestService
    ) {

    }

    public function index(): JsonResponse
    {
        try {
            $requests = $this->requestService->getAllRequests(Auth::user());
            return response()->success($requests);
        } catch (\Exception $e) {
            return response()->error($e->getMessage(), 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'address' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
            'priority' => ['nullable', Rule::enum(RequestPriority::class)],
            'contact_phone' => 'nullable|string|max:20',
            'project_id' => 'nullable|exists:projects,id',
        ]);

        try {
            $rescueRequest = $this->requestService->createRequest($validated, Auth::user());
            return response()->success(
                $rescueRequest->load(['user', 'assignedRescuer']),
                'Request created successfully',
                201
            );
        } catch (\Exception $e) {
            return response()->error($e->getMessage(), 400);
        }
    }

    public function show(int $id): JsonResponse
    {
        try {
            $request = $this->requestService->getRequestById($id, Auth::user());
            return response()->success($request);
        } catch (\Exception $e) {
            return response()->error($e->getMessage(), 403);
        }
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::enum(RequestStatus::class)],
            'priority' => ['nullable', Rule::enum(RequestPriority::class)],
            'assigned_rescuer_id' => 'nullable|exists:users,id',
            'description' => 'nullable|string|max:1000',
        ]);

        try {
            $rescueRequest = RequestModel::findOrFail($id);
            $updatedRequest = $this->requestService->updateRequest($rescueRequest, $validated, Auth::user());
            return response()->success($updatedRequest, 'Request updated successfully');
        } catch (\Exception $e) {
            return response()->error($e->getMessage(), 403);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        try {
            $request = RequestModel::findOrFail($id);
            $cancelledRequest = $this->requestService->cancelRequest($request, Auth::user());
            return response()->success($cancelledRequest, 'Request cancelled successfully');
        } catch (\Exception $e) {
            return response()->error($e->getMessage(), 403);
        }
    }

    public function assignRescuer(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'rescuer_id' => 'required|exists:users,id'
        ]);

        try {
            $rescueRequest = RequestModel::findOrFail($id);
            $rescuer = User::findOrFail($validated['rescuer_id']);
            $updatedRequest = $this->requestService->assignRescuer($rescueRequest, $rescuer, Auth::user());
            return response()->success($updatedRequest, 'Rescuer assigned successfully');
        } catch (\Exception $e) {
            return response()->error($e->getMessage(), 403);
        }
    }
}
