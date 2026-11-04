<?php

namespace App\Http\Controllers;

use App\Services\JobPostingFetcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Prefills the job-application form from a posting URL.
 *
 * Returns JSON rather than an Inertia redirect: the form stays on screen and
 * the user reviews/edits what was extracted before anything is saved.
 */
class JobPostingImportController extends Controller
{
    public function __construct(private readonly JobPostingFetcher $fetcher) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'url' => ['required', 'string', 'max:2000'],
        ]);

        try {
            $posting = $this->fetcher->fetch($validated['url']);
        } catch (\RuntimeException $e) {
            return response()->json([
                'message' => $this->message($e->getMessage()),
            ], 422);
        }

        return response()->json(['posting' => $posting]);
    }

    private function message(string $code): string
    {
        return match ($code) {
            'invalid_url' => 'URL tidak valid. Gunakan alamat http:// atau https://.',
            'blocked_host' => 'Alamat tersebut tidak dapat diakses dari server kami.',
            'blocked_by_site' => 'Situs lowongan ini memblokir pengambilan otomatis. Tempel isi lowongan secara manual.',
            'no_text' => 'Halaman berhasil dibuka, tetapi teks lowongan tidak ditemukan. Tempel isinya secara manual.',
            default => 'Gagal membuka halaman lowongan. Coba lagi atau tempel isinya secara manual.',
        };
    }
}
