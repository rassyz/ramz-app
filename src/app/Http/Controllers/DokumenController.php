<?php

namespace App\Http\Controllers;

use App\Models\Dokumen;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Pengunduhan berkas dokumen pendukung. Berkas disimpan privat pada
 * disk lokal (bukan URL publik), sehingga setiap unduhan melewati
 * pemeriksaan DokumenPolicy di sini.
 */
class DokumenController extends Controller
{
    public function download(Dokumen $dokumen): BinaryFileResponse
    {
        Gate::authorize('view', $dokumen);

        $disk = Storage::disk('local');

        abort_unless($disk->exists($dokumen->path_file), 404);

        return response()->download(
            $disk->path($dokumen->path_file),
            $dokumen->nama_file_asli
        );
    }
}
