<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class SetupSuperAdminController extends Controller
{
    public function __invoke(Request $request)
    {
        if ($request->query('token') !== env('SETUP_TOKEN')) {
            abort(403);
        }

        Artisan::call('superadmin:create', [
            '--name' => 'Emerson',
            '--email' => 'emersonfigg@gmail.com',
            '--password' => 'Fig182103@',
        ]);

        return response()->json([
            'status' => 'ok',
            'output' => Artisan::output(),
        ]);
    }
}
