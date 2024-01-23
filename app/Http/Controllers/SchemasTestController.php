<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateSchemasTestRequest;
use App\Http\Requests\UpdateSchemasTestRequest;
use App\Http\Controllers\AppBaseController;
use App\Repositories\SchemasTestRepository;
use Illuminate\Http\Request;
use Flash;

class SchemasTestController extends AppBaseController
{
    /** @var SchemasTestRepository $schemasTestRepository*/
    private $schemasTestRepository;

    public function __construct(SchemasTestRepository $schemasTestRepo)
    {
        $this->schemasTestRepository = $schemasTestRepo;
    }

    /**
     * Display a listing of the SchemasTest.
     */
    public function index(Request $request)
    {
        $schemasTests = $this->schemasTestRepository->paginate(10);

        return view('schemas_tests.index')
            ->with('schemasTests', $schemasTests);
    }

    /**
     * Show the form for creating a new SchemasTest.
     */
    public function create()
    {
        return view('schemas_tests.create');
    }

    /**
     * Store a newly created SchemasTest in storage.
     */
    public function store(CreateSchemasTestRequest $request)
    {
        $input = $request->all();

        $schemasTest = $this->schemasTestRepository->create($input);

        Flash::success('Schemas Test saved successfully.');

        return redirect(route('schemasTests.index'));
    }

    /**
     * Display the specified SchemasTest.
     */
    public function show($id)
    {
        $schemasTest = $this->schemasTestRepository->find($id);

        if (empty($schemasTest)) {
            Flash::error('Schemas Test not found');

            return redirect(route('schemasTests.index'));
        }

        return view('schemas_tests.show')->with('schemasTest', $schemasTest);
    }

    /**
     * Show the form for editing the specified SchemasTest.
     */
    public function edit($id)
    {
        $schemasTest = $this->schemasTestRepository->find($id);

        if (empty($schemasTest)) {
            Flash::error('Schemas Test not found');

            return redirect(route('schemasTests.index'));
        }

        return view('schemas_tests.edit')->with('schemasTest', $schemasTest);
    }

    /**
     * Update the specified SchemasTest in storage.
     */
    public function update($id, UpdateSchemasTestRequest $request)
    {
        $schemasTest = $this->schemasTestRepository->find($id);

        if (empty($schemasTest)) {
            Flash::error('Schemas Test not found');

            return redirect(route('schemasTests.index'));
        }

        $schemasTest = $this->schemasTestRepository->update($request->all(), $id);

        Flash::success('Schemas Test updated successfully.');

        return redirect(route('schemasTests.index'));
    }

    /**
     * Remove the specified SchemasTest from storage.
     *
     * @throws \Exception
     */
    public function destroy($id)
    {
        $schemasTest = $this->schemasTestRepository->find($id);

        if (empty($schemasTest)) {
            Flash::error('Schemas Test not found');

            return redirect(route('schemasTests.index'));
        }

        $this->schemasTestRepository->delete($id);

        Flash::success('Schemas Test deleted successfully.');

        return redirect(route('schemasTests.index'));
    }
}
