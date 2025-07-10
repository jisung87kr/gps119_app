<?php

namespace App\Services;

use App\Models\Request;
use App\Models\User;
use App\Enums\RequestStatus;
use App\Enums\RequestPriority;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request as HttpRequest;

class RequestService
{
    public function getAllRequests(User $user): Collection
    {
        if ($user->hasRole('admin') || $user->hasRole('rescuer')) {
            return Request::with(['user', 'assignedRescuer'])
                ->orderBy('priority', 'desc')
                ->orderBy('requested_at', 'desc')
                ->get();
        }

        return $user->requests()->with(['assignedRescuer'])->get();
    }

    public function createRequest(array $data, User $user): Request
    {
        $requestData = array_merge($data, [
            'user_id' => $user->id,
            'status' => RequestStatus::PENDING,
            'requested_at' => now(),
        ]);

        return Request::create($requestData);
    }

    public function updateRequest(Request $request, array $data, User $user): Request
    {
        if (!$user->hasRole('admin') && !$user->hasRole('rescuer') && !$request->isOwner($user)) {
            throw new \Exception('Unauthorized to update this request');
        }

        $request->update($data);

        if (isset($data['status']) && $data['status'] === RequestStatus::IN_PROGRESS && !$request->responded_at) {
            $request->update(['responded_at' => now()]);
        }

        if (isset($data['status']) && $data['status'] === RequestStatus::COMPLETED && !$request->completed_at) {
            $request->update(['completed_at' => now()]);
        }

        return $request->fresh(['user', 'assignedRescuer']);
    }

    public function cancelRequest(Request $request, User $user): Request
    {
        if (!$request->isOwner($user) && !$user->hasRole('admin')) {
            throw new \Exception('Unauthorized to cancel this request');
        }

        if (!$request->canBeCancelled()) {
            throw new \Exception('Request cannot be cancelled in current status');
        }

        $request->update([
            'status' => RequestStatus::CANCELLED,
            'completed_at' => now(),
        ]);

        return $request->fresh(['user', 'assignedRescuer']);
    }

    public function assignRescuer(Request $request, User $rescuer, User $admin): Request
    {
        if (!$admin->hasRole('admin') && !$admin->hasRole('rescuer')) {
            throw new \Exception('Unauthorized to assign rescuer');
        }

        if (!$rescuer->hasRole('rescuer')) {
            throw new \Exception('Selected user is not a rescuer');
        }

        $request->update([
            'assigned_rescuer_id' => $rescuer->id,
            'status' => RequestStatus::IN_PROGRESS,
            'responded_at' => now(),
        ]);

        return $request->fresh(['user', 'assignedRescuer']);
    }

    public function getRequestById(int $id, User $user): Request
    {
        $request = Request::with(['user', 'assignedRescuer'])->findOrFail($id);

        if (!$user->hasRole('admin') && !$user->hasRole('rescuer') && !$request->isOwner($user)) {
            throw new \Exception('Unauthorized to view this request');
        }

        return $request;
    }
}