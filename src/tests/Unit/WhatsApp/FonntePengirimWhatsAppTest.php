<?php

namespace Tests\Unit\WhatsApp;

use App\Services\WhatsApp\FonntePengirimWhatsApp;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

class FonntePengirimWhatsAppTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.whatsapp.fonnte.token' => 'token-uji',
            'services.whatsapp.fonnte.base_url' => 'https://api.fonnte.com',
        ]);
    }

    public function test_kirim_mengirim_ke_fonnte_dengan_nomor_ternormalisasi(): void
    {
        Http::fake([
            'api.fonnte.com/*' => Http::response(['status' => true, 'id' => [123]], 200),
        ]);

        (new FonntePengirimWhatsApp)->kirim('0812-3456-7890', 'Judul Uji', 'Isi uji.');

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.fonnte.com/send'
            && $request->hasHeader('Authorization', 'token-uji')
            && $request['target'] === '6281234567890'
            && $request['message'] === "*Judul Uji*\n\nIsi uji."
            && (string) $request['countryCode'] === '0');
    }

    public function test_kirim_menerima_format_plus62_dan_spasi(): void
    {
        Http::fake([
            'api.fonnte.com/*' => Http::response(['status' => true, 'id' => [124]], 200),
        ]);

        (new FonntePengirimWhatsApp)->kirim('+62 812 3456 7890', 'Judul', 'Isi.');

        Http::assertSent(fn (Request $request): bool => $request['target'] === '6281234567890');
    }

    public function test_kirim_melempar_saat_fonnte_menolak(): void
    {
        Http::fake([
            'api.fonnte.com/*' => Http::response(['status' => false, 'reason' => 'token invalid'], 200),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('token invalid');

        (new FonntePengirimWhatsApp)->kirim('081234567890', 'Judul', 'Isi.');
    }

    public function test_kirim_melempar_saat_token_kosong_dan_tidak_mengirim_apa_pun(): void
    {
        config(['services.whatsapp.fonnte.token' => '']);

        Http::fake();

        try {
            (new FonntePengirimWhatsApp)->kirim('081234567890', 'Judul', 'Isi.');
            $this->fail('RuntimeException seharusnya dilempar saat token kosong.');
        } catch (RuntimeException) {
            Http::assertNothingSent();
        }
    }

    public function test_kirim_melempar_saat_http_gagal(): void
    {
        Http::fake([
            'api.fonnte.com/*' => Http::response('server error', 500),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('HTTP 500');

        (new FonntePengirimWhatsApp)->kirim('081234567890', 'Judul', 'Isi.');
    }

    public function test_kirim_melempar_saat_nomor_tidak_valid(): void
    {
        Http::fake();

        $this->expectException(InvalidArgumentException::class);

        (new FonntePengirimWhatsApp)->kirim('12345', 'Judul', 'Isi.');
    }
}
