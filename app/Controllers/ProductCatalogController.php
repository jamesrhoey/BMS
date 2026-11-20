<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\ProductCatalog;
use App\Services\ActivityLogger;
use App\Services\AuthorizationService;
use Throwable;

final class ProductCatalogController extends Controller
{
    public function index(Request $request, array $params = []): void
    {
        $user = $this->requireUser();
        if (!AuthorizationService::canAccessIatms($user)) {
            $this->denyAccess($request, 'Unauthorized access to Product Catalog.');
        }

        $page = max(1, (int) $request->query('page', '1'));
        $filters = [
            'search' => (string) $request->query('search', ''),
            'category_id' => (string) $request->query('category_id', ''),
            'is_serialized' => (string) $request->query('is_serialized', ''),
        ];

        $result = ProductCatalog::paginate($filters, $page, 20);

        $this->render('iatms/products/index', [
            'pageTitle' => 'Product Catalog',
            'activeNav' => 'iatms_products',
            'products' => $result['data'],
            'pagination' => $result,
            'filters' => $filters,
            'categories' => ProductCatalog::categories(),
            'canManage' => AuthorizationService::canManageIatmsCatalog($user),
        ]);
    }

    public function create(Request $request, array $params = []): void
    {
        $user = $this->requireUser();
        if (!AuthorizationService::canManageIatmsCatalog($user)) {
            $this->denyAccess($request, 'Unauthorized access to create Product.');
        }

        $this->render('iatms/products/form', [
            'pageTitle' => 'Add Product Model',
            'activeNav' => 'iatms_products',
            'product' => null,
            'categories' => ProductCatalog::categories(),
        ]);
    }

    public function store(Request $request, array $params = []): void
    {
        $user = $this->requireUser();
        if (!AuthorizationService::canManageIatmsCatalog($user)) {
            $this->denyAccess($request, 'Unauthorized attempt to create Product.');
        }

        try {
            $id = ProductCatalog::create($request->allPost());
            ActivityLogger::log('PRODUCT_CREATED', 'inventory_item', $id, "Created catalog product #{$id}.", $request, (int) $user['id'], false);
            Session::flash('success', 'Product model created successfully.');
            Response::redirect('/inventory/products');
        } catch (Throwable $e) {
            Session::flash('error', $e->getMessage());
            Response::redirect('/inventory/products/create');
        }
    }

    public function edit(Request $request, array $params = []): void
    {
        $user = $this->requireUser();
        if (!AuthorizationService::canManageIatmsCatalog($user)) {
            $this->denyAccess($request, 'Unauthorized access to edit Product.');
        }

        $id = (int) ($params['id'] ?? 0);
        $product = ProductCatalog::find($id);
        if (!$product) {
            $this->notFound('Product model not found.');
        }

        $this->render('iatms/products/form', [
            'pageTitle' => 'Edit Product Model',
            'activeNav' => 'iatms_products',
            'product' => $product,
            'categories' => ProductCatalog::categories(),
        ]);
    }

    public function update(Request $request, array $params = []): void
    {
        $user = $this->requireUser();
        if (!AuthorizationService::canManageIatmsCatalog($user)) {
            $this->denyAccess($request, 'Unauthorized attempt to update Product.');
        }

        $id = (int) ($params['id'] ?? 0);
        try {
            ProductCatalog::update($id, $request->allPost());
            ActivityLogger::log('PRODUCT_UPDATED', 'inventory_item', $id, "Updated catalog product #{$id}.", $request, (int) $user['id'], false);
            Session::flash('success', 'Product model updated successfully.');
            Response::redirect('/inventory/products');
        } catch (Throwable $e) {
            Session::flash('error', $e->getMessage());
            Response::redirect("/inventory/products/{$id}/edit");
        }
    }
}
