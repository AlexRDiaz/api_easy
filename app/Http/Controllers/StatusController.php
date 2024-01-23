<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateStatusRequest;
use App\Http\Requests\UpdateStatusRequest;
use App\Http\Controllers\AppBaseController;
use App\Repositories\StatusRepository;
use Illuminate\Http\Request;
use Flash;

class StatusController extends AppBaseController
{
    /** @var StatusRepository $statusRepository*/
    private $statusRepository;

    public function __construct(StatusRepository $statusRepo)
    {
        $this->statusRepository = $statusRepo;
    }

    /**
     * Display a listing of the Status.
     */
    public function index(Request $request)
    {
        $statuses = $this->statusRepository->paginate(10);

        return view('statuses.index')
            ->with('statuses', $statuses);
    }

    /**
     * Show the form for creating a new Status.
     */
    public function create()
    {
        return view('statuses.create');
    }

    /**
     * Store a newly created Status in storage.
     */
    public function store(CreateStatusRequest $request)
    {
        $input = $request->all();

        $status = $this->statusRepository->create($input);

        Flash::success('Status saved successfully.');

        return redirect(route('statuses.index'));
    }

    /**
     * Display the specified Status.
     */
    public function show($id)
    {
        $status = $this->statusRepository->find($id);

        if (empty($status)) {
            Flash::error('Status not found');

            return redirect(route('statuses.index'));
        }

        return view('statuses.show')->with('status', $status);
    }

    /**
     * Show the form for editing the specified Status.
     */
    public function edit($id)
    {
        $status = $this->statusRepository->find($id);

        if (empty($status)) {
            Flash::error('Status not found');

            return redirect(route('statuses.index'));
        }

        return view('statuses.edit')->with('status', $status);
    }

    /**
     * Update the specified Status in storage.
     */
    public function update($id, UpdateStatusRequest $request)
    {
        $status = $this->statusRepository->find($id);

        if (empty($status)) {
            Flash::error('Status not found');

            return redirect(route('statuses.index'));
        }

        $status = $this->statusRepository->update($request->all(), $id);

        Flash::success('Status updated successfully.');

        return redirect(route('statuses.index'));
    }

    /**
     * Remove the specified Status from storage.
     *
     * @throws \Exception
     */
    public function destroy($id)
    {
        $status = $this->statusRepository->find($id);

        if (empty($status)) {
            Flash::error('Status not found');

            return redirect(route('statuses.index'));
        }

        $this->statusRepository->delete($id);

        Flash::success('Status deleted successfully.');

        return redirect(route('statuses.index'));
    }
}
