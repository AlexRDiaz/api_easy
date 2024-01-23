<?php

namespace App\Http\Controllers;

use App\Http\Requests\Createpedidos_shopifiesRequest;
use App\Http\Requests\Updatepedidos_shopifiesRequest;
use App\Http\Controllers\AppBaseController;
use App\Repositories\pedidos_shopifiesRepository;
use Illuminate\Http\Request;
use Flash;

class pedidos_shopifiesController extends AppBaseController
{
    /** @var pedidos_shopifiesRepository $pedidosShopifiesRepository*/
    private $pedidosShopifiesRepository;

    public function __construct(pedidos_shopifiesRepository $pedidosShopifiesRepo)
    {
        $this->pedidosShopifiesRepository = $pedidosShopifiesRepo;
    }

    /**
     * Display a listing of the pedidos_shopifies.
     */
    public function index(Request $request)
    {
        $pedidosShopifies = $this->pedidosShopifiesRepository->paginate(10);

        return view('pedidos_shopifies.index')
            ->with('pedidosShopifies', $pedidosShopifies);
    }

    /**
     * Show the form for creating a new pedidos_shopifies.
     */
    public function create()
    {
        return view('pedidos_shopifies.create');
    }

    /**
     * Store a newly created pedidos_shopifies in storage.
     */
    public function store(Createpedidos_shopifiesRequest $request)
    {
        $input = $request->all();

        $pedidosShopifies = $this->pedidosShopifiesRepository->create($input);

        Flash::success('Pedidos Shopifies saved successfully.');

        return redirect(route('pedidosShopifies.index'));
    }

    /**
     * Display the specified pedidos_shopifies.
     */
    public function show($id)
    {
        $pedidosShopifies = $this->pedidosShopifiesRepository->find($id);

        if (empty($pedidosShopifies)) {
            Flash::error('Pedidos Shopifies not found');

            return redirect(route('pedidosShopifies.index'));
        }

        return view('pedidos_shopifies.show')->with('pedidosShopifies', $pedidosShopifies);
    }

    /**
     * Show the form for editing the specified pedidos_shopifies.
     */
    public function edit($id)
    {
        $pedidosShopifies = $this->pedidosShopifiesRepository->find($id);

        if (empty($pedidosShopifies)) {
            Flash::error('Pedidos Shopifies not found');

            return redirect(route('pedidosShopifies.index'));
        }

        return view('pedidos_shopifies.edit')->with('pedidosShopifies', $pedidosShopifies);
    }

    /**
     * Update the specified pedidos_shopifies in storage.
     */
    public function update($id, Updatepedidos_shopifiesRequest $request)
    {
        $pedidosShopifies = $this->pedidosShopifiesRepository->find($id);

        if (empty($pedidosShopifies)) {
            Flash::error('Pedidos Shopifies not found');

            return redirect(route('pedidosShopifies.index'));
        }

        $pedidosShopifies = $this->pedidosShopifiesRepository->update($request->all(), $id);

        Flash::success('Pedidos Shopifies updated successfully.');

        return redirect(route('pedidosShopifies.index'));
    }

    /**
     * Remove the specified pedidos_shopifies from storage.
     *
     * @throws \Exception
     */
    public function destroy($id)
    {
        $pedidosShopifies = $this->pedidosShopifiesRepository->find($id);

        if (empty($pedidosShopifies)) {
            Flash::error('Pedidos Shopifies not found');

            return redirect(route('pedidosShopifies.index'));
        }

        $this->pedidosShopifiesRepository->delete($id);

        Flash::success('Pedidos Shopifies deleted successfully.');

        return redirect(route('pedidosShopifies.index'));
    }
}
