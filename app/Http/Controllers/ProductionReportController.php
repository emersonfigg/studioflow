<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;

class ProductionReportController extends Controller
{
    /**
     * Redirect legacy production route to the finance production module.
     */
    public function index(Request $request): RedirectResponse
    {
        return redirect()->route('finance.production', $request->query());
    }
}
