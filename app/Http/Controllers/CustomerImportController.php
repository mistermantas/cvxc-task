<?php

namespace App\Http\Controllers;

use App\Services\CustomerCsvImport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CustomerImportController extends Controller
{
    public function index(Request $request, CustomerCsvImport $import): Response
    {
        $preview = null;
        $path = $request->session()->get('customer_import.path');

        if ($path && Storage::disk('local')->exists($path)) {
            try {
                $preview = $import->analyze(Storage::disk('local')->path($path));
            } catch (ValidationException $exception) {
                Storage::disk('local')->delete($path);
                $request->session()->forget('customer_import');
            }
        }

        return Inertia::render('Import/Index', [
            'preview' => $preview,
            'filename' => $preview ? $request->session()->get('customer_import.name') : null,
            'summary' => $request->session()->get('summary'),
        ]);
    }

    public function preview(Request $request, CustomerCsvImport $import): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ], [
            'file.required' => 'Pasirinkite CSV failą.',
            'file.file' => 'Pasirinkite tinkamą failą.',
            'file.mimes' => 'Galima įkelti tik CSV failą.',
            'file.max' => 'Failas negali viršyti 2 MB.',
        ]);

        $oldPath = $request->session()->get('customer_import.path');
        $path = $request->file('file')->store('customer-imports', 'local');

        if ($path === false) {
            throw ValidationException::withMessages(['file' => 'Nepavyko išsaugoti failo.']);
        }

        try {
            $import->analyze(Storage::disk('local')->path($path));
        } catch (ValidationException $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }

        if ($oldPath) {
            Storage::disk('local')->delete($oldPath);
        }

        $request->session()->put('customer_import', [
            'path' => $path,
            'name' => $request->file('file')->getClientOriginalName(),
        ]);

        return to_route('home');
    }

    public function store(Request $request, CustomerCsvImport $import): RedirectResponse
    {
        $path = $request->session()->get('customer_import.path');

        if (! $path || ! Storage::disk('local')->exists($path)) {
            throw ValidationException::withMessages(['file' => 'Įkelkite CSV failą iš naujo.']);
        }

        $analysis = $import->analyze(Storage::disk('local')->path($path));
        $imported = 0;

        DB::transaction(function () use ($analysis, &$imported): void {
            foreach ($analysis['rows'] as $row) {
                if ($row['errors'] !== []) {
                    continue;
                }

                $imported += DB::table('customers')->insertOrIgnore([
                    ...$row['data'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        Storage::disk('local')->delete($path);
        $request->session()->forget('customer_import');

        return to_route('home')->with('summary', [
            'total' => $analysis['total'],
            'imported' => $imported,
            'failed' => $analysis['total'] - $imported,
        ]);
    }

    public function cancel(Request $request): RedirectResponse
    {
        $path = $request->session()->get('customer_import.path');

        if ($path) {
            Storage::disk('local')->delete($path);
        }

        $request->session()->forget('customer_import');

        return to_route('home');
    }
}
