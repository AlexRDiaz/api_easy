<?php

use App\Http\Controllers\API\CarrierCoverageAPIController;
use App\Http\Controllers\API\CarrierExternalAPIController;
use App\Http\Controllers\API\CoverageExternalAPIController;
use App\Http\Controllers\API\DBBackUpAPIController;
use App\Http\Controllers\API\DpaProvinciaAPIController;
use App\Http\Controllers\API\ExchangeRateAPIController;
use App\Http\Controllers\API\GenerateReportAPIController;
use App\Http\Controllers\API\IntegrationAPIController;
use App\Http\Controllers\API\OrdenesRetiroAPIController;
use App\Http\Controllers\API\PedidosShopifyAPIController;
use App\Http\Controllers\API\ProductAPIController;
use App\Http\Controllers\API\ProductsSellerLinkAPIController;
use App\Http\Controllers\API\ProviderAPIController;
use App\Http\Controllers\API\ProviderTransactionsAPIController;
use App\Http\Controllers\API\ReserveAPIController;
use App\Http\Controllers\API\RutaAPIController;
use App\Http\Controllers\API\StockHistoryAPIController;
use App\Http\Controllers\API\SubRutaAPIController;
use App\Http\Controllers\API\OperadoreAPIController;
use App\Http\Controllers\API\PedidosProductLinkAPIController;
use App\Http\Controllers\API\PedidosShopifiesCarrierExternalLinkAPIController;
use App\Http\Controllers\API\TransaccionPedidoTransportadoraAPIController;
use App\Http\Controllers\API\TransportadorasShippingCostAPIController;
use App\Http\Controllers\API\UpUserAPIController;
use App\Http\Controllers\API\VendedoreAPIController;
use App\Http\Controllers\API\WarehouseAPIController;

use App\Http\Controllers\API\ShopifyWebhookAPIController;
use App\Http\Controllers\API\TransaccionesAPIController;
use App\Http\Controllers\API\TransaccionesGlobalAPIController;
use App\Http\Controllers\API\TransportadorasAPIController;
use App\Http\Controllers\API\UpUsersWarehouseLinkAPIController;
use App\Models\Reserve;
use App\Models\UpUsersWarehouseLink;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::middleware(['cors'])->group(function () {

    Route::resource('pedidos-shopify', PedidosShopifyAPIController::class)
        ->except(['create', 'edit']);

    Route::post('pedidos-shopify/filter/logistic', [App\Http\Controllers\API\PedidosShopifyAPIController::class, 'getByDateRangeLogistic']);

    Route::post('logistic/filter/novelties', [App\Http\Controllers\API\PedidosShopifyAPIController::class, 'getByDateRangeLogisticNovelties']);
    Route::post('logistic/filter/novelties-vendedores', [App\Http\Controllers\API\PedidosShopifyAPIController::class, 'getVendedoresByDateRange']);
    Route::post('logistic/filter/novelties-aux', [App\Http\Controllers\API\PedidosShopifyAPIController::class, 'getByDateRangeAuditAndResolvedNovelties']);


    Route::post('logistic/orders-pdf', [App\Http\Controllers\API\PedidosShopifyAPIController::class, 'getByDateRangeOrdersforAudit']);
    // Route::post('logistic/orders-pdf', [App\Http\Controllers\API\PedidosShopifyAPIController::class, 'exportOrdersToExcel']);



    // ! update status and comment
    Route::post('logistic/update-status-comment', [App\Http\Controllers\API\PedidosShopifyAPIController::class, 'updateOrderStatusAndComment']);


    Route::resource('orden_retiro', App\Http\Controllers\API\OrdenesRetiroAPIController::class)
        ->except(['create', 'edit']);
    //  ************************* LOGISTIC **************************



    // ! esta es solo para ver lo que tiene registrado en cada usuario en los permisos
    Route::get('permisos', [App\Http\Controllers\API\UpUserAPIController::class, 'getPermisos']);

    // * obtiene los datos de cada rol con su id y accesos
    Route::get('access-total', [App\Http\Controllers\API\RolesFrontAPIController::class, 'index']);
    // ! accesos en base al id proporcionado
    Route::get('access-ofid/{id}', [App\Http\Controllers\API\RolesFrontAPIController::class, 'getRoleById']);

    // ! getPermissionsSellerPrincipalforNewSeller
    Route::get('sellerprincipal-for-newseller/{id}', [App\Http\Controllers\API\UpUserAPIController::class, 'getPermissionsSellerPrincipalforNewSeller']);

    Route::post('edit-personal-access', [App\Http\Controllers\API\UpUserAPIController::class, 'managePermission']);


    Route::post('new-access', [App\Http\Controllers\API\UpUserAPIController::class, 'updatePermissions']);

    // eliminacion de accesos enviando el active con false
    Route::post('dlt-rolesaccess', [App\Http\Controllers\API\UpUserAPIController::class, 'deletePermissions']);


    Route::post('upd-rolesaccess', [App\Http\Controllers\API\UpUserAPIController::class, 'newPermission']);

    // ! generate roles
    Route::get('getespc-access/{rol}', [App\Http\Controllers\API\RolesFrontAPIController::class, 'getAccesofEspecificRol']);

    // * --> PRINTEDGUIDES

    Route::post('pedidos-shopifies-prtgd', [App\Http\Controllers\API\PedidosShopifyAPIController::class, 'getOrdersForPrintedGuidesLaravel']);
    Route::post('pedidos-shopifies-prtgdD', [App\Http\Controllers\API\PedidosShopifyAPIController::class, 'getOrdersForPrintedGuidesLaravelD']);
    Route::post('pedidos-shopifies-prtgdO', [App\Http\Controllers\API\PedidosShopifyAPIController::class, 'getOrdersForPrintedGuidesLaravelO']);

    Route::post('values_not_test', [App\Http\Controllers\API\PedidosShopifyAPIController::class, 'getOrdersCountByWarehouse']);
    Route::post('values-test-operators', [App\Http\Controllers\API\PedidosShopifyAPIController::class, 'getOrdersCountByWarehouseByOrders']);

    Route::post('upd/pedidossho-printedg', [App\Http\Controllers\API\PedidosShopifyAPIController::class, 'updateOrderInteralStatusLogisticLaravel']);

    Route::post('upd/pedidossho-LogisticStatusPrint', [App\Http\Controllers\API\PedidosShopifyAPIController::class, 'updateOrderLogisticStatusPrintLaravel']);

    Route::post('order', [App\Http\Controllers\API\PedidosShopifyAPIController::class, 'getByID']);

    // * --> GUIDES_SENT

    Route::post('send-guides/printg', [App\Http\Controllers\API\PedidosShopifyAPIController::class, 'getOrdersForPrintGuidesInSendGuidesPrincipalLaravel']);
    Route::post('send-guides/printgD', [App\Http\Controllers\API\PedidosShopifyAPIController::class, 'getOrdersForPrintGuidesInSendGuidesPrincipalLaravelD']);
    Route::post('send-guides', [App\Http\Controllers\API\PedidosShopifyAPIController::class, 'getOrdersSendGuides']);
    // getOrdersSendGuides


    //  *************************   SELLER          *****************

    Route::get('pedidos-shopifies/{id}', [App\Http\Controllers\API\PedidosShopifyAPIController::class, 'getOrderbyId']);
    // updateDateandStatus
    Route::post('upd/pedidos-shopifies', [App\Http\Controllers\API\PedidosShopifyAPIController::class, 'updateDateandStatus']);



    // ********************************************************
    // ! PARA AUDIT VALUES
    Route::post('logistic/values/getByDateRangeValuesAudit', [App\Http\Controllers\API\PedidosShopifyAPIController::class, 'getByDateRangeValuesAudit']);
    // ! ↓ REGISTRO DE PEDIDOS
    Route::post('pedidos-shopifies', [App\Http\Controllers\API\PedidosShopifyAPIController::class, 'postOrdersPricipalOrders']);

    // ? para la fecha del pedido
    Route::post('shopify/pedidos', [App\Http\Controllers\API\PedidosShopifyAPIController::class, 'createDateOrderLaravel']);

    // ! ↓ traer todos los pedidos Laravel
    Route::post('new-pedidos-shopifies', [App\Http\Controllers\API\PedidosShopifyAPIController::class, 'getPrincipalOrdersSellersFilterLaravel']);
    // ! ↓ PEDIDOS updateOrderInternalStatus
    Route::post('updOiS/pedidos-shopifies', [App\Http\Controllers\API\PedidosShopifyAPIController::class, 'updateOrderInternalStatus']);

    // ! ↓ PEDIDOS updateOrderInfoSellerLaravel
    Route::post('updtOrdIS/pedidos-shopifies', [App\Http\Controllers\API\PedidosShopifyAPIController::class, 'updateOrderInfoSellerLaravel']);

    Route::post('pedidos-shopify/filter', [App\Http\Controllers\API\PedidosShopifyAPIController::class, 'getByDateRange']);

    Route::post('pedidos-shopify/referer-value', [App\Http\Controllers\API\PedidosShopifyAPIController::class, 'getRefererTotalValue']);




    Route::post('pedidos-shopify/update-gestioned-novelty/{id}', [App\Http\Controllers\API\PedidosShopifyAPIController::class, 'updateGestionedNovelty']);

    Route::post('pedidos-shopify/update-gestioned-payment-cost-delivery', [App\Http\Controllers\API\PedidosShopifyAPIController::class, 'updateGestionedPaymentCostDelivery']);

    Route::post('pedidos-shopify/update-gestioned-payment-cost-delivery-unique/{id}', [App\Http\Controllers\API\PedidosShopifyAPIController::class, 'updateGestionedPaymentCostDeliveryU']);




    Route::post('pedidos-shopify/update-prop-gestioned-novelty/{id}', [App\Http\Controllers\API\PedidosShopifyAPIController::class, 'updateOrCreatePropertyGestionedNovelty']);




    //  ! ↓ LA ORIGINAL
    Route::post('integrations/put-integrations-url-store/compare-token', [IntegrationAPIController::class, 'putIntegrationsUrlStore']);
    Route::post('integrations/get-integrations-url-store/get-token', [IntegrationAPIController::class, 'getIntegrationsByStorename']);



    Route::post('/shopify/webhooks/customer_data_request', [ShopifyWebhookAPIController::class, 'handleCustomerDataRequest']);
    Route::post('/shopify/webhooks/customer_redact', [ShopifyWebhookAPIController::class, 'handleCustomerRedact']);
    Route::post('/shopify/webhooks/shop_redact', [ShopifyWebhookAPIController::class, 'handleShopRedact']);


    Route::middleware(['jwt.auth'])->group(function () {

        Route::put('/users/modify-account/{id}', [UpUserAPIController::class, 'modifyAccount']);

        Route::put('/users/update-paiment-information/{id}', [UpUserAPIController::class, 'updatePaymentInformation']);
        Route::get('/users/get-paiment-information/{id}', [UpUserAPIController::class, 'getPaymentInformation']);
        Route::get('/users/get-paiment-information-from-withdrawal/{id}', [UpUserAPIController::class, 'getPaymentInformationfromWithdrawal']);


        Route::get('integrations/user/{id}', [IntegrationAPIController::class, 'getIntegrationsByUser']);
        Route::put('integrations/put-integrations-url-store', [IntegrationAPIController::class, 'putIntegrationsUrlStore']);



        Route::resource('integrations', IntegrationAPIController::class)
            ->except(['create', 'edit']);




        Route::resource('reserves', App\Http\Controllers\API\ReserveAPIController::class)
            ->except(['create', 'edit']);


        Route::prefix('reserves')->group(function () {
            Route::get('/', [ReserveAPIController::class, 'index']);
            // Route::post('/find-by-product-and-sku', [ReserveAPIController::class, 'findByProductAndSku']);


            Route::put('/{id}', [OrdenesRetiroAPIController::class, 'update']);
            Route::post('/', [OrdenesRetiroAPIController::class, 'store']);
        });
    });




    //  ! MIA OPERATOR
    Route::post('operator/filter', [App\Http\Controllers\API\PedidosShopifyAPIController::class, 'getDevolucionesOperator']);
    //  ! MIA TRANSPORTADORAS

    Route::post('transportadoras', [App\Http\Controllers\API\TransportadorasAPIController::class, 'getTransportadoras']); //newVersionByCompany
    Route::post('transportadoras-novelties', [App\Http\Controllers\API\TransportadorasAPIController::class, 'getTransportadorasNovelties']);
    Route::post('active/transportadoras', [App\Http\Controllers\API\TransportadorasAPIController::class, 'getActiveTransportadoras']);
    Route::post('generaldata', [App\Http\Controllers\API\TransportadorasAPIController::class, 'generalData']);
    Route::post('generaldata-optimized', [TransportadorasAPIController::class, 'generalDataOptimized']);
    Route::post('generalspecific-data', [App\Http\Controllers\API\TransportadorasAPIController::class, 'getSpecificDataGeneral']);
    Route::post('transportadoras/update-supervisor', [App\Http\Controllers\API\TransportadorasAPIController::class, 'updateSupervisor']);
    Route::get('transportadoras/get-supervisors', [App\Http\Controllers\API\TransportadorasAPIController::class, 'getSupervisors']);

    // ! MIA OPERATORESBYTRANSPORT

    Route::get('operatoresbytransport/{id}', [App\Http\Controllers\API\TransportadorasAPIController::class, 'getOperatoresbyTransport']);

    // ! MIA VENDEDORES

    Route::post('vendedores', [App\Http\Controllers\API\VendedoreAPIController::class, 'getVendedores']);

    Route::post('vendedores-sld', [App\Http\Controllers\API\VendedoreAPIController::class, 'getSaldoPorId']);


    // *
    Route::put('/vendedores/{id}', [App\Http\Controllers\API\VendedoreAPIController::class, 'update']);
    Route::put('/vendedores-cost/{id}', [App\Http\Controllers\API\VendedoreAPIController::class, 'updateRefererCost']);

    Route::get('/vendedores/saldo/{id}', [VendedoreAPIController::class, 'getSaldo']);
    Route::get('/proveedores/saldo/{id}', [App\Http\Controllers\API\ProviderAPIController::class, 'getSaldoP']);
    Route::get('/vendedores/refereds/{id}', [VendedoreAPIController::class, 'getRefereds']);
    Route::get('/vendedores-principals', [App\Http\Controllers\API\VendedoreAPIController::class, 'obtenerUsuariosPrincipales']);

    // ! transacciones_globales

    Route::post("transacciones-global/saldo", [\App\Http\Controllers\API\TransaccionesGlobalAPIController::class, 'getSaldoActualSellerTG']);



    // ! TRANSACCIONES
    Route::get("transacciones", [\App\Http\Controllers\API\TransaccionesAPIController::class, 'index']);
    // ! LAST 30
    Route::get("transacciones-lst", [\App\Http\Controllers\API\TransaccionesAPIController::class, 'last30rows']);
    // ! CREDIT TRANSACTION
    Route::post("transacciones/credit", [\App\Http\Controllers\API\TransaccionesAPIController::class, 'Credit']);
    // ! CREDIT TRANSACTION
    Route::post("transacciones/debit", [\App\Http\Controllers\API\TransaccionesAPIController::class, 'Debit']);
    Route::post("transacciones/payment-order-delivered", [\App\Http\Controllers\API\TransaccionesAPIController::class, 'paymentOrderDelivered']);
    Route::post("transacciones/payment-order-not-delivered", [\App\Http\Controllers\API\TransaccionesAPIController::class, 'paymentOrderNotDelivered']);
    Route::post("transacciones/payment-order-with-novelty/{id}", [\App\Http\Controllers\API\TransaccionesAPIController::class, 'paymentOrderWithNovelty']);
    Route::post("transacciones/payment-order-operator-in-office/{id}", [\App\Http\Controllers\API\TransaccionesAPIController::class, 'paymentOrderOperatorInOffice']);
    Route::post("transacciones/payment-logistic-in-warehouse/{id}", [\App\Http\Controllers\API\TransaccionesAPIController::class, 'paymentLogisticInWarehouse']);
    Route::post("transacciones/debit_withdrawal/{id}", [\App\Http\Controllers\API\TransaccionesAPIController::class, 'debitWithdrawal']);

    Route::post("transacciones/payment-order-in-warehouse-provider/{id}", [TransaccionesAPIController::class, 'paymentOrderInWarehouseProvider']);

    Route::post("transacciones/payment-transport-by-return-status/{id}", [\App\Http\Controllers\API\TransaccionesAPIController::class, 'paymentTransportByReturnStatus']);
    Route::post("transacciones/payment-logistic-by-return-status/{id}", [\App\Http\Controllers\API\TransaccionesAPIController::class, 'paymentLogisticByReturnStatus']);
    Route::post('transacciones/withdrawal-provider-aproved/{id}', [TransaccionesAPIController::class, 'postWhitdrawalProviderAproved']);
    Route::post('transacciones/deny-withdrawal/{id}', [TransaccionesAPIController::class, 'denyWithdrawal']);
    Route::post('transacciones/get-transactions', [TransaccionesAPIController::class, 'getTransactions']);



    // ! ***********************

    // !  TRANSACTIONS BY ID SELLER
    Route::get("transacciones/bySeller/{id}", [\App\Http\Controllers\API\TransaccionesAPIController::class, 'getTransactionsById']);
    // ! ***********************
    // !  Rollback transactions
    Route::post("transacciones/rollback", [\App\Http\Controllers\API\TransaccionesAPIController::class, 'rollbackTransaction']);
    // !  Rollback transactions-global
    Route::post("transacciones-global/rollback", [\App\Http\Controllers\API\TransaccionesAPIController::class, 'rollbackTransactionGlobal']);

    // ? pedido programado ************
    Route::post("transacciones/pedido-programado", [\App\Http\Controllers\API\TransaccionesAPIController::class, 'pedidoProgramado']);
    // ? ------------------------------
    // ! ***********************
    // ! GetExistTransactions
    Route::post("transacciones/exist", [\App\Http\Controllers\API\TransaccionesAPIController::class, 'getExistTransaction']);

    // ! GetTransacctions by date
    Route::post("transacciones/by-date", [\App\Http\Controllers\API\TransaccionesAPIController::class, 'getTransactionsByDate']);
    // ! GetTransacctions To rollback
    Route::get("transacciones/to-rollback/{id}", [\App\Http\Controllers\API\TransaccionesAPIController::class, 'getTransactionToRollback']);
    // ! GetTransacctionsGlobal To rollback
    Route::get("transacciones-global/to-rollback/{id}", [\App\Http\Controllers\API\TransaccionesAPIController::class, 'getTransactionGlobalToRollback']);

    Route::post("transacciones/cleanTransactionsFailed/{id}", [\App\Http\Controllers\API\TransaccionesAPIController::class, 'cleanTransactionsFailed']);






    Route::post('pedidos-shopify/filter/sellers', [App\Http\Controllers\API\PedidosShopifyAPIController::class, 'getReturnSellers']);

    Route::post('/valuesdrop', [PedidosShopifyAPIController::class, 'getValuesDropdownSendGuide']);
    Route::post('/valuesdrop-op', [PedidosShopifyAPIController::class, 'getValuesDropdownSendGuideOp']);

    Route::post('/register-withdrawan-by', [PedidosShopifyAPIController::class, 'addWithdrawanBy']);

    Route::post('/update-retirement-status', [PedidosShopifyAPIController::class, 'updateRetirementStatus']);

    Route::post('/end-retirement', [PedidosShopifyAPIController::class, 'endRetirement']);


    Route::post('pedidos-shopify/products/counters', [App\Http\Controllers\API\PedidosShopifyAPIController::class, 'getCounters']);
    Route::post('pedidos-shopify/products/counters/logistic', [App\Http\Controllers\API\PedidosShopifyAPIController::class, 'getCountersLogistic']);

    Route::post('pedidos-shopify/routes/count', [App\Http\Controllers\API\PedidosShopifyAPIController::class, 'getProductsDashboardRoutesCount']);
    Route::post('pedidos-shopify/products/values/transport', [App\Http\Controllers\API\PedidosShopifyAPIController::class, 'CalculateValuesTransport']);
    Route::post('pedidos-shopify/products/values/seller', [App\Http\Controllers\API\PedidosShopifyAPIController::class, 'CalculateValuesSeller']);
    Route::post('pedidos-shopify/products/values/provider', [App\Http\Controllers\API\PedidosShopifyAPIController::class, 'CalculateValuesProvider']);
    Route::post('pedidos-shopify/values/external_carrier', [App\Http\Controllers\API\PedidosShopifyAPIController::class, 'CalculateValuesExternalCarrier']);


    Route::post('pedidos-shopify/testChatby', [App\Http\Controllers\API\PedidosShopifyAPIController::class, 'testChatby']);


    // *
    Route::post('shopify/pedidos/{id}', [App\Http\Controllers\API\PedidosShopifyAPIController::class, 'shopifyPedidos']);


    Route::post('seller/invoice', [App\Http\Controllers\API\VendedoreAPIController::class, 'mybalanceVF']);

    Route::get('user/verifyterms/{id}', [App\Http\Controllers\API\UpUserAPIController::class, 'verifyTerms']);
    Route::put('user/updateterms/{id}', [App\Http\Controllers\API\UpUserAPIController::class, 'updateAcceptedTerms']);

    // -- wallet-ordenesretiro

    Route::get('seller/misaldo/{id}', [App\Http\Controllers\API\MiSaldoAPIController::class, 'getSaldo']);
    // *
    Route::put('pedidos-shopify/{id}', [App\Http\Controllers\API\PedidosShopifyAPIController::class, 'update']);


    Route::put('pedidos-shopify/update/{id}', [App\Http\Controllers\API\PedidosShopifyAPIController::class, 'updateCampo']);
    Route::put('pedidos-shopify/update/status/{id}', [App\Http\Controllers\API\PedidosShopifyAPIController::class, 'updateStatus']);


    //Route::resource('/users', App\Http\Controllers\API\UpUserAPIController::class);
    Route::post('/users', [UpUserAPIController::class, 'store']);
    Route::post('/all-referers-of', [UpUserAPIController::class, 'allReferers']);
    Route::post('/users/general', [UpUserAPIController::class, 'storeGeneral']);
    Route::post('/users/providers', [App\Http\Controllers\API\UpUserAPIController::class, 'storeProvider']);
    Route::put('/users/providers/{id}', [App\Http\Controllers\API\UpUserAPIController::class, 'updateProvider']);
    Route::put('/users/transports/{id}', [App\Http\Controllers\API\UpUserAPIController::class, 'updateTransport']);
    Route::put('/users/sellers/{id}', [App\Http\Controllers\API\UpUserAPIController::class, 'updateSeller']);
    Route::put('/users/operator/{id}', [App\Http\Controllers\API\UpUserAPIController::class, 'updateOperator']);
    Route::put('/users/logistic-users/{id}', [App\Http\Controllers\API\UpUserAPIController::class, 'updateLogisticUser']);


    Route::get('/users/subproviders/{id}/{search?}', [App\Http\Controllers\API\UpUserAPIController::class, 'getSubProviders']);
    Route::post('/users/subproviders/add', [App\Http\Controllers\API\UpUserAPIController::class, 'storeSubProvider']);
    Route::put('/users/subproviders/update/{id}', [App\Http\Controllers\API\UpUserAPIController::class, 'updateSubProvider']);

    Route::put('/users/autome/{id}', [App\Http\Controllers\API\UpUserAPIController::class, 'editAutome']);

    Route::put('/users/{id}', [UpUserAPIController::class, 'update']);

    Route::get('/users/master/{id}', [UpUserAPIController::class, 'getSellerMaster']);
    Route::get('/users/handle-callback', [UpUserAPIController::class, 'handleCallback']);

    Route::post('/users/generate-integration', [UpUserAPIController::class, 'generateIntegration']);

    Route::post('/users/new-user', [UpUserAPIController::class, 'storeGeneralNewUser']);

    Route::post('/users/reset-password/{id}', [UpUserAPIController::class, 'updateUserPassword']);




    Route::post('/login', [UpUserAPIController::class, 'login']);


    Route::get('users/{id}', [UpUserAPIController::class, 'users']);

    Route::post('users/update-active/{id}', [UpUserAPIController::class, 'updateUserActiveStatus']);
    Route::post('users/update-trans-active/{id}', [UpUserAPIController::class, 'updateUserActiveStatusTransport']);
    //  *
    Route::post('users/userbyemail', [UpUserAPIController::class, 'userByEmail']);

    Route::get('users/pdf/{id}', [App\Http\Controllers\API\UpUserAPIController::class, 'userspdf']);


    Route::get('/sellers/{id}/{search?}', [UpUserAPIController::class, 'getSellers']);

    Route::post('/report', [GenerateReportAPIController::class, 'generateExcel']);







    Route::prefix('seller/ordenesretiro')->group(function () {
        Route::post('/retiro/{id}', [OrdenesRetiroAPIController::class, 'getOrdenesRetiroNew']);
        Route::get('/ret-count/{id}', [OrdenesRetiroAPIController::class, 'getOrdenesRetiroCount']);
        Route::post('/{id}', [OrdenesRetiroAPIController::class, 'getOrdenesRetiro']);
        Route::post('/withdrawal/{id}', [OrdenesRetiroAPIController::class, 'withdrawal']);
        Route::post('/withdrawal-provider/{id}', [OrdenesRetiroAPIController::class, 'withdrawalProvider']);
        Route::post('/withdrawal-provider-aproved-n/{id}', [TransaccionesAPIController::class, 'postWhitdrawalSellerAproved']);
        Route::put('/withdrawal/update-intern/{id}', [OrdenesRetiroAPIController::class, 'putIntern']);
        Route::put('/withdrawal/denied/{id}', [OrdenesRetiroAPIController::class, 'putRechazado']);
        Route::get('/count-a-r/{id}', [OrdenesRetiroAPIController::class, 'getCountOrders']);
        Route::post('/withdrawal-n/generate-code', [OrdenesRetiroAPIController::class, 'postWithdrawalProvider']);
    });

    /* for new version
    Route::prefix('seller/ordenesretiro')->group(function () {
        Route::get('/', [OrdenesRetiroAPIController::class, 'index']);
        Route::get('/retiro/{id}', [OrdenesRetiroAPIController::class, 'getOrdenesRetiroNew']);
        Route::get('/ret-count/{id}', [OrdenesRetiroAPIController::class, 'getOrdenesRetiroCount']);

        Route::post('/{id}', [OrdenesRetiroAPIController::class, 'getOrdenesRetiro']);
        Route::post('/withdrawal/generate-code', [OrdenesRetiroAPIController::class, 'postWithdrawalProvider']);
        Route::post('/withdrawal/{id}', [OrdenesRetiroAPIController::class, 'withdrawal']);
        Route::put('/withdrawal/done/{id}', [OrdenesRetiroAPIController::class, 'putRealizado']);

        //  *
        Route::get('/totalforsellers', [OrdenesRetiroAPIController::class, 'totalForSellers']);
        Route::post('/updaterealizado/{id}', [OrdenesRetiroAPIController::class, 'updateRealizado']);

    });
    */




    Route::prefix('generate-reports')->group(function () {
        Route::get('/', [GenerateReportAPIController::class, 'index']);
        Route::get('/{id}', [GenerateReportAPIController::class, 'show']);
        Route::post('/', [GenerateReportAPIController::class, 'store']);
        Route::put('/{id}', [GenerateReportAPIController::class, 'update']);
        Route::delete('/{id}', [GenerateReportAPIController::class, 'destroy']);

        Route::get('/seller/{id}', [GenerateReportAPIController::class, 'getBySeller']);
    });

    // *
    Route::prefix('rutas')->group(function () {
        Route::get('/', [RutaAPIController::class, 'index']);
        Route::get('/active/{id}', [RutaAPIController::class, 'activeRoutes']);
        Route::get('/{id}', [RutaAPIController::class, 'show']);
        Route::post('/subroutesofroute/{id}', [RutaAPIController::class, 'getTransportadorasConRutasYSubRutas']);
        Route::post('create', [RutaAPIController::class, 'create']);
    });


    Route::prefix('subrutas')->group(function () {
        Route::get('/', [SubRutaAPIController::class, 'index']);
        Route::get('/withid', [SubRutaAPIController::class, 'getSubrutas']);
        Route::get('bytransport/{id}', [SubRutaAPIController::class, 'getSubroutesByTransportadoraId']);
        Route::post('operadores/{id}', [SubRutaAPIController::class, 'getOperatorsbySubrouteAndTransportadora']);
        Route::post('/new', [SubRutaAPIController::class, 'store']);
    });

    Route::prefix('operators')->group(function () {
        Route::get('/', [OperadoreAPIController::class, 'getOperators']);
        Route::get('/by-id/{idOperator}', [OperadoreAPIController::class, 'getOperatorbyId']);
    });



    // upUsersPedidos
    //testgetUserPedidos
    // getUserPedidos

    Route::get('up-user-pedidos/{id}', [UpUserAPIController::class, 'getUserPedidos']);


    // !general stats

    Route::post('data-stats-rt', [App\Http\Controllers\API\TransportStatsAPIController::class, 'fetchDataByDate3']);
    Route::post('data-stats', [App\Http\Controllers\API\TransportStatsAPIController::class, 'fetchDataByDate2']);

    // TEST ↓↓
    // Route::post('up-user-pedidos-gnral', [App\Http\Controllers\API\PedidosShopifyAPIController::class, 'generateTransportStatsTR']);




    // *
    Route::get('transportadorasbyroute/{id}', [App\Http\Controllers\API\TransportadorasAPIController::class, 'getTransportsByRoute']);
    Route::put('pedidos-shopify/updateroutetransport/{id}', [App\Http\Controllers\API\PedidosShopifyAPIController::class, 'updateOrderRouteAndTransport']);
    Route::put('pedidos-shopify/updatesubrouteoperator/{id}', [App\Http\Controllers\API\PedidosShopifyAPIController::class, 'updateOrderSubRouteAndOperator']);
    //  *
    Route::post('pedidos-shopify/filterall', [App\Http\Controllers\API\PedidosShopifyAPIController::class, 'getByDateRangeAll']);
    Route::post('pedidos-shopify/filterall/external', [App\Http\Controllers\API\PedidosShopifyAPIController::class, 'getByDateRangeAllExternal']);
    Route::post('pedidos-shopify/update-payment-cost-delivery', [App\Http\Controllers\API\PedidosShopifyAPIController::class, 'updateVerifyPaymentCostDelivery']);
    Route::put('pedidos-shopify/update-payment-cost-delivery/ind/{id}', [App\Http\Controllers\API\PedidosShopifyAPIController::class, 'updateVerifyPaymentCostDeliveryInd']);
    //  *  delete   
    //  *


    Route::prefix('shippingcost')->group(function () {
        Route::get('/', [TransportadorasShippingCostAPIController::class, 'index']);
        Route::post('/', [TransportadorasShippingCostAPIController::class, 'store']);
        Route::post('/getbydate', [TransportadorasShippingCostAPIController::class, 'byDate']);
        Route::post('/{id}', [App\Http\Controllers\API\TransportadorasShippingCostAPIController::class, 'getByTransportadora']);
        Route::put('/{id}', [TransportadorasShippingCostAPIController::class, 'update']);
        Route::get('/perday', [App\Http\Controllers\API\TransportadorasShippingCostAPIController::class, 'getShippingCostPerDay']);
        Route::post('bytransportadora/{id}', [App\Http\Controllers\API\TransportadorasShippingCostAPIController::class, 'getByTransportadora']);
        Route::post('bytransportadoraexternal/{id}', [App\Http\Controllers\API\TransportadorasShippingCostAPIController::class, 'getByTransportadoraExternal']);
    });

    // 
    Route::prefix('transaccionespedidotransportadora')->group(function () {
        Route::get('/', [TransaccionPedidoTransportadoraAPIController::class, 'index']);
        Route::post('/getByDate', [TransaccionPedidoTransportadoraAPIController::class, 'getByDate']);
        Route::post('/', [TransaccionPedidoTransportadoraAPIController::class, 'store']);
        Route::put('/{id}', [TransaccionPedidoTransportadoraAPIController::class, 'upd ate']);
        Route::post('/bydates', [TransaccionPedidoTransportadoraAPIController::class, 'getByTransportadoraDates']);
        Route::post('/bydates-external', [TransaccionPedidoTransportadoraAPIController::class, 'getByTransportadoraDatesExternal']);
        Route::delete('/{id}', [TransaccionPedidoTransportadoraAPIController::class, 'destroy']);
        Route::post('/ordersperday', [TransaccionPedidoTransportadoraAPIController::class, 'getOrdersPerDay']);
        // Route::post('/ordersperdayexternal', [TransportadorasShippingCostAPIController::class, 'getOrdersPerDayExternal']);
        // ! para testear la cronometrizada de las transportadoaras externas
        // Route::post('/ordersperdayexternal', [TransportadorasShippingCostAPIController::class, 'getShippingCostCarrierExternalPerDay']);
    });

    Route::prefix('providers')->group(function () {

        Route::post('/all', [ProviderAPIController::class, 'getProviders']);
        Route::get('/nofilter/{idCompany}', [ProviderAPIController::class, 'index']);

        Route::put('/update/{id}', [ProviderAPIController::class, 'updateRequest']);
    });

    // *
    Route::prefix('productseller')->group(function () {
        Route::post('/', [ProductsSellerLinkAPIController::class, 'store']);
        Route::post('/get', [ProductsSellerLinkAPIController::class, 'getProductSeller']);
        Route::put('/{id}', [ProductsSellerLinkAPIController::class, 'update']);
        Route::put('/delete/{id}', [ProductsSellerLinkAPIController::class, 'destroy']);
    });

    //  *
    Route::prefix('reserve')->group(function () {
        Route::post('/', [ReserveAPIController::class, 'store']);
        Route::put('/editstock', [ReserveAPIController::class, 'editStock']);
        Route::put('/admin', [ReserveAPIController::class, 'admin']);
    });

    //  *
    Route::prefix('providertransaction')->group(function () {
        Route::post('provider', [ProviderTransactionsAPIController::class, 'getAll']);
        Route::get('retiros/{id}', [ProviderTransactionsAPIController::class, 'getTotalRetiros']);
        Route::post('valuespendingextcarrier', [ProviderTransactionsAPIController::class, 'calculateValuesPendingExternalCarrier']);
    });

    //  * test email
    // Route::post('sendemail', [App\Http\Controllers\API\OrdenesRetiroAPIController::class, 'sendEmail']);
    Route::post('sendemail', [App\Http\Controllers\API\PasswordAPIController::class, 'sendResetEmail']);
    Route::get('/reset-password/{token}', [App\Http\Controllers\API\PasswordAPIController::class, 'showResetForm']);
    Route::post('/reset-password', [App\Http\Controllers\API\PasswordAPIController::class, 'resetPassword']);



    //  *
    Route::prefix('transportadora')->group(function () {
        Route::get('rutas/{id}', [TransportadorasAPIController::class, 'getRutasByCarrier']);
    });

    //  *
    Route::prefix('transacciones')->group(function () {
        Route::post('approvewithdrawal/{id}', [TransaccionesAPIController::class, 'approveWhitdrawal']);
    });

    //  *
    Route::prefix('provincias')->group(function () {
        Route::get('/{id}', [DpaProvinciaAPIController::class, 'index']);
        // Route::get('cantones/{id}', [DpaProvinciaAPIController::class, 'getCantones']);
        Route::get('coverages/{id}', [DpaProvinciaAPIController::class, 'getCoverages']);
    });

    //  *
    Route::prefix('carrierexternal')->group(function () {
        Route::post('/all', [CarrierExternalAPIController::class, 'index']);
        // Route::get('cantones/{id}', [DpaProvinciaAPIController::class, 'getCantones']);
        Route::get('/active', [CarrierExternalAPIController::class, 'getCarriersExternal']);
        Route::get('/{id}', [CarrierExternalAPIController::class, 'show']);
        Route::post('/', [CarrierExternalAPIController::class, 'store']);
        Route::put('/{id}', [CarrierExternalAPIController::class, 'update']);
        Route::post('/coveragebyprov', [CarrierExternalAPIController::class, 'getCoverageByProvincia']);
        Route::post('/newcoverage', [CarrierExternalAPIController::class, 'newCoverage']);
        Route::post('multinewcoverage', [CarrierExternalAPIController::class, 'multiNewCoverage']);
    });

    //  *
    Route::prefix('carriercoverage')->group(function () {
        Route::post('/all', [CarrierCoverageAPIController::class, 'getAll']);
        Route::put('/{id}', [CarrierCoverageAPIController::class, 'update']);
        Route::post('/search', [CarrierCoverageAPIController::class, 'getDataCoverage']);
    });

    //new test
    Route::get('external/getbyid/{id}', [App\Http\Controllers\API\CarrierExternalAPIController::class, 'showById']);
    Route::post('coveragescarrier/all', [App\Http\Controllers\API\CarrierCoverageAPIController::class, 'getAllSimple']);
    Route::get('coverages/all', [App\Http\Controllers\API\CoverageExternalAPIController::class, 'index']);

    //  * 
    Route::post('orderproduct', [App\Http\Controllers\API\PedidosShopifyAPIController::class, 'orderProducto']);

    Route::post('/gintracom/postorder', [IntegrationAPIController::class, 'putIntegrationsUrlStoreG']);
    Route::post('gintracom/updatestate', [IntegrationAPIController::class, 'requestUpdateState']);
    Route::post('/gintracom/getlabel', [IntegrationAPIController::class, 'getLabelGTM']);
    Route::post('/gintracom/multilabel', [IntegrationAPIController::class, 'getMultiLabels']);
    Route::post('/gintracom/solucion', [IntegrationAPIController::class, 'putSolucionGTM']);

    //  * 
    Route::post('upuserswarehouse', [App\Http\Controllers\API\UpUsersWarehouseLinkAPIController::class, 'store']);
    Route::put('upuserswarehouse/update/{id}', [App\Http\Controllers\API\UpUsersWarehouseLinkAPIController::class, 'update']);


    Route::post('allbysubprov', [ProductAPIController::class, 'getBySubProvider']);
    Route::post('productwarehouse', [App\Http\Controllers\API\ProductWarehouseLinkAPIController::class, 'store']);
    Route::get('warehouses/specials/{id}', [WarehouseAPIController::class, 'getSpecials']);
    Route::put('productwarehouse/update', [App\Http\Controllers\API\ProductWarehouseLinkAPIController::class, 'update']);
    Route::post('catalog/all', [ProductAPIController::class, 'getProductsNew']);

    //*
    Route::delete('pedidos-shopify/deleteroutetransport/{id}', [App\Http\Controllers\API\PedidosShopifyAPIController::class, 'deleteRouteAndTransport']);

    //  *
    Route::prefix('ordercarrier')->group(function () {
        Route::post('/', [PedidosShopifiesCarrierExternalLinkAPIController::class, 'store']);
        Route::put('/{id}', [PedidosShopifiesCarrierExternalLinkAPIController::class, 'update']);
        Route::put('byorder/{id}', [PedidosShopifiesCarrierExternalLinkAPIController::class, 'updateByOrder']);
        Route::delete('/{id}', [PedidosShopifiesCarrierExternalLinkAPIController::class, 'destroy']); //not yet
        Route::get('/{id}', [PedidosShopifiesCarrierExternalLinkAPIController::class, 'show']);
    });

    //  * transacciones global
    Route::post('generaldata-tg', [App\Http\Controllers\API\TransaccionesGlobalAPIController::class, 'generalData']);


    //  *
    Route::get('dbbackup', [DBBackUpAPIController::class, 'db_backup']);
    Route::delete('rutatransp/delete/{id}', [PedidosShopifyAPIController::class, 'rutaTranspDestroy']); //*

    Route::post('checkstock', [ProductAPIController::class, 'getProductVariantStock']);
    Route::get('subprov/warehouses/{id}', [UpUsersWarehouseLinkAPIController::class, 'show']);
    Route::post('productsbyids', [ProductAPIController::class, 'getData']);
    Route::get('owners/{id}', [ProductAPIController::class, 'getOwners']);
    Route::get('prodwarehouses/{id}', [ProductAPIController::class, 'getWarehouses']);
    Route::get('warehousessubprov/{idSub}', [WarehouseAPIController::class, 'bySubprov']);


    //*
    Route::post('easywp/newuser', [UpUserAPIController::class, 'storeUserWP']);
    Route::post('bystorage', [ProductAPIController::class, 'getByStorage']);
    Route::prefix('orderproduct')->group(function () {
        Route::post('create', [PedidosProductLinkAPIController::class, 'store']);
        Route::delete('delete', [PedidosProductLinkAPIController::class, 'destroy']);
    });
    Route::post('sendemailconfirm/{idOrder}', [PedidosShopifyAPIController::class, 'sendEmailConfirmtoProvider']);

    //  *
    Route::prefix('integration')->group(function () {
        Route::post('orderlaar', [IntegrationAPIController::class, 'postOrderLaar']);
        Route::post('laar/getlabel', [IntegrationAPIController::class, 'getLabelLaar']);
        Route::post('multilabel', [IntegrationAPIController::class, 'getMultiLabelsGeneral']);
        Route::put('laar/uptnovelty', [IntegrationAPIController::class, 'uptNoveltyLaar']);
    });

    //******** */
    Route::post('/authenticate', [IntegrationAPIController::class, 'authenticate']);


    //  *
    Route::prefix('cities')->group(function () {
        Route::post('/search', [CoverageExternalAPIController::class, 'search']);
    });

    // *
    Route::post('laarcourier/updatestate', [IntegrationAPIController::class, 'requestUpdateStateLaar']);
    // *
    Route::post('transaccionesglobal/valuespendingextcarrier', [TransaccionesGlobalAPIController::class, 'calculateValuesPendingExternalCarrier']);
    Route::post('pedidos-shopify/updatepaymentbyidexternal', [PedidosShopifyAPIController::class, 'updateGestionedPaymentCostDeliveryByIdExternal']);

    //  *
    Route::prefix('ciudades')->group(function () {
        Route::post('byprovincia', [CoverageExternalAPIController::class, 'byProvincia']);
    });

    // *
    Route::get('laar/searchupdate', [IntegrationAPIController::class, 'searchUpdateStateLaar']);

    //  *
    Route::prefix('exchangerate')->group(function () {
        Route::get('bycountry/{idCountry}', [ExchangeRateAPIController::class, 'byCountry']);
        Route::post('update', [ExchangeRateAPIController::class, 'updateData']);

    });

    //..
});




// api/upload
//Route::get('/tu-ruta', 'TuController@tuMetodo')->middleware('cors');

Route::post('upload', [App\Http\Controllers\API\TransportadorasShippingCostAPIController::class, 'uploadFile']);
//      *
Route::put('pedidos-shopify/updatefieldtime/{id}', [App\Http\Controllers\API\PedidosShopifyAPIController::class, 'updateFieldTime']);

Route::prefix('warehouses')->group(function () {
    Route::get('bycompany/{id}', [WarehouseAPIController::class, 'index']);
    Route::get('/{id}', [WarehouseAPIController::class, 'show']);
    Route::post('/', [WarehouseAPIController::class, 'store']);
    Route::put('/{id}', [WarehouseAPIController::class, 'update']);
    Route::delete('/deactivate/{id}', [WarehouseAPIController::class, 'deactivate']);
    Route::post('/activate/{id}', [WarehouseAPIController::class, 'activate']);
    Route::get('/provider/{id}', [WarehouseAPIController::class, 'filterByProvider']);
    Route::post('/approved', [WarehouseAPIController::class, 'approvedWarehouses']);
    Route::post('/foroperators', [PedidosShopifyAPIController::class, 'getWarehousesofOrders']);
});

// *
Route::prefix('products')->group(function () {
    Route::get('/', [ProductAPIController::class, 'index']);
    Route::post('/all', [ProductAPIController::class, 'getProducts']);
    Route::post('/by/{id}', [ProductAPIController::class, 'getProductsByProvider']);
    // ! cambio stock -> variant_details
    Route::post('/updatestock', [ProductAPIController::class, 'updateProductVariantStock']);
    Route::post('/{id}', [ProductAPIController::class, 'show']);
    Route::post('/', [ProductAPIController::class, 'store']);
    Route::put('/{id}', [ProductAPIController::class, 'update']);
    Route::put('delete/{id}', [ProductAPIController::class, 'destroy']);
    Route::put('update/{id}', [ProductAPIController::class, 'updateRequest']);
    Route::get('avaliabledelete/{id}', [ProductAPIController::class, 'avaliableDelete']);
});

Route::prefix('stockhistory')->group(function () {
    Route::post('/', [StockHistoryAPIController::class, 'store']);
    Route::post('/v2', [StockHistoryAPIController::class, 'storeD']);
    Route::post('byproduct/{id}', [StockHistoryAPIController::class, 'showByProduct']);
});


Route::post('operadoresoftransport', [App\Http\Controllers\API\UpUserAPIController::class, 'getOperatorsTransportLaravel']);




Route::resource('products', App\Http\Controllers\API\ProductAPIController::class)
    ->except(['create', 'edit']);

Route::resource('providers', App\Http\Controllers\API\ProviderAPIController::class)
    ->except(['create', 'edit']);

Route::resource('up-users-providers-links', App\Http\Controllers\API\UpUsersProvidersLinkAPIController::class)
    ->except(['create', 'edit']);
