<?php

namespace App\Http\Controllers\API;

use App\Http\Requests\API\CreateUpUsersProvidersLinkAPIRequest;
use App\Http\Requests\API\UpdateUpUsersProvidersLinkAPIRequest;
use App\Models\UpUsersProvidersLink;
use App\Repositories\UpUsersProvidersLinkRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\AppBaseController;

/**
 * Class UpUsersProvidersLinkAPIController
 */
class UpUsersProvidersLinkAPIController extends AppBaseController
{
    private UpUsersProvidersLinkRepository $upUsersProvidersLinkRepository;

    public function __construct(UpUsersProvidersLinkRepository $upUsersProvidersLinkRepo)
    {
        $this->upUsersProvidersLinkRepository = $upUsersProvidersLinkRepo;
    }

    /**
     * Display a listing of the UpUsersProvidersLinks.
     * GET|HEAD /up-users-providers-links
     */
    public function index(Request $request): JsonResponse
    {
        $upUsersProvidersLinks = $this->upUsersProvidersLinkRepository->all(
            $request->except(['skip', 'limit']),
            $request->get('skip'),
            $request->get('limit')
        );

        return $this->sendResponse($upUsersProvidersLinks->toArray(), 'Up Users Providers Links retrieved successfully');
    }

    /**
     * Store a newly created UpUsersProvidersLink in storage.
     * POST /up-users-providers-links
     */
    public function store(CreateUpUsersProvidersLinkAPIRequest $request): JsonResponse
    {
        $input = $request->all();

        $upUsersProvidersLink = $this->upUsersProvidersLinkRepository->create($input);

        return $this->sendResponse($upUsersProvidersLink->toArray(), 'Up Users Providers Link saved successfully');
    }

    /**
     * Display the specified UpUsersProvidersLink.
     * GET|HEAD /up-users-providers-links/{id}
     */
    public function show($id): JsonResponse
    {
        /** @var UpUsersProvidersLink $upUsersProvidersLink */
        $upUsersProvidersLink = $this->upUsersProvidersLinkRepository->find($id);

        if (empty($upUsersProvidersLink)) {
            return $this->sendError('Up Users Providers Link not found');
        }

        return $this->sendResponse($upUsersProvidersLink->toArray(), 'Up Users Providers Link retrieved successfully');
    }

    /**
     * Update the specified UpUsersProvidersLink in storage.
     * PUT/PATCH /up-users-providers-links/{id}
     */
    public function update($id, UpdateUpUsersProvidersLinkAPIRequest $request): JsonResponse
    {
        $input = $request->all();

        /** @var UpUsersProvidersLink $upUsersProvidersLink */
        $upUsersProvidersLink = $this->upUsersProvidersLinkRepository->find($id);

        if (empty($upUsersProvidersLink)) {
            return $this->sendError('Up Users Providers Link not found');
        }

        $upUsersProvidersLink = $this->upUsersProvidersLinkRepository->update($input, $id);

        return $this->sendResponse($upUsersProvidersLink->toArray(), 'UpUsersProvidersLink updated successfully');
    }

    /**
     * Remove the specified UpUsersProvidersLink from storage.
     * DELETE /up-users-providers-links/{id}
     *
     * @throws \Exception
     */
    public function destroy($id): JsonResponse
    {
        /** @var UpUsersProvidersLink $upUsersProvidersLink */
        $upUsersProvidersLink = $this->upUsersProvidersLinkRepository->find($id);

        if (empty($upUsersProvidersLink)) {
            return $this->sendError('Up Users Providers Link not found');
        }

        $upUsersProvidersLink->delete();

        return $this->sendSuccess('Up Users Providers Link deleted successfully');
    }
}
