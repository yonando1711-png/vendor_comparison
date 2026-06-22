<?php

namespace App\Http\Controllers;

use App\Models\MasterSupplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MasterSupplierController extends Controller
{
    public function index()
    {
        $suppliers = MasterSupplier::orderBy('name')->get();
        $user = Auth::user();
        $canManage = $user->isAdmin() || $user->isCreator();
        return view('master-suppliers.index', compact('suppliers', 'canManage'));
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        if (! ($user->isAdmin() || $user->isCreator())) {
            abort(403, 'Only administrators or purchasing staff can add master suppliers.');
        }

        $data = $request->validate([
            'name'    => ['required', 'string', 'max:255'],
            'street'  => ['nullable', 'string', 'max:500'],
            'street2' => ['nullable', 'string', 'max:500'],
            'city'    => ['nullable', 'string', 'max:255'],
            'phone'   => ['nullable', 'string', 'max:50'],
            'mobile'  => ['nullable', 'string', 'max:50'],
            'email'   => ['nullable', 'email', 'max:255'],
            'notes'   => ['nullable', 'string', 'max:2000'],
        ]);

        $data['created_by'] = Auth::id();
        $data['is_active']  = true;

        MasterSupplier::create($data);

        return redirect()->route('master-suppliers.index')
            ->with('success', 'Supplier berhasil ditambahkan.');
    }

    public function update(Request $request, MasterSupplier $masterSupplier)
    {
        $user = Auth::user();
        if (! ($user->isAdmin() || $user->isCreator())) {
            abort(403, 'Only administrators or purchasing staff can update master suppliers.');
        }

        $data = $request->validate([
            'name'    => ['required', 'string', 'max:255'],
            'street'  => ['nullable', 'string', 'max:500'],
            'street2' => ['nullable', 'string', 'max:500'],
            'city'    => ['nullable', 'string', 'max:255'],
            'phone'   => ['nullable', 'string', 'max:50'],
            'mobile'  => ['nullable', 'string', 'max:50'],
            'email'   => ['nullable', 'email', 'max:255'],
            'notes'   => ['nullable', 'string', 'max:2000'],
            'is_active' => ['boolean'],
        ]);

        $masterSupplier->update($data);

        return redirect()->route('master-suppliers.index')
            ->with('success', 'Supplier berhasil diperbarui.');
    }

    public function destroy(MasterSupplier $masterSupplier)
    {
        $user = Auth::user();
        if (! ($user->isAdmin() || $user->isCreator())) {
            abort(403, 'Only administrators or purchasing staff can delete master suppliers.');
        }

        $masterSupplier->delete();

        return redirect()->route('master-suppliers.index')
            ->with('success', 'Supplier berhasil dihapus.');
    }
}
