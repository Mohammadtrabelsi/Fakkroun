<?php

namespace Tests\Feature\Integrations;

use App\Models\Post;
use App\Models\User;
use App\Services\PdfService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;
use Tests\TestCase;

class PdfTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_downloads_a_post_as_pdf_with_the_shared_layout(): void
    {
        Pdf::fake();
        $admin = User::factory()->admin()->create();
        $post = Post::factory()->create(['title' => ['en' => 'Quarterly update'], 'body' => ['en' => "Line one\nLine two"]]);

        $this->actingAs($admin)->get(route('admin.posts.pdf', $post))->assertOk();

        Pdf::assertRespondedWithPdf(function (PdfBuilder $pdf) use ($post) {
            return $pdf->viewName === 'pdf.post'
                && $pdf->isDownload()
                && $pdf->downloadName === 'post-'.$post->slug.'.pdf'
                && $pdf->contains(['Quarterly update', 'Line one<br />', __('pdf.post_document')]);
        });
    }

    public function test_preview_is_served_inline(): void
    {
        Pdf::fake();
        $post = Post::factory()->create();

        $this->actingAs(User::factory()->admin()->create())->get(route('admin.posts.pdf', $post).'?preview=1')->assertOk();

        Pdf::assertRespondedWithPdf(fn (PdfBuilder $pdf) => $pdf->isInline() && $pdf->downloadName === 'post-'.$post->slug.'.pdf');
    }

    public function test_pdf_requires_authorization(): void
    {
        $post = Post::factory()->create();

        $this->get(route('admin.posts.pdf', $post))->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->get(route('admin.posts.pdf', $post))->assertForbidden();
    }

    public function test_dompdf_renders_a_real_document(): void
    {
        $post = Post::factory()->create(['title' => ['en' => 'Real render']]);

        $response = $this->actingAs(User::factory()->admin()->create())->get(route('admin.posts.pdf', $post));

        $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $this->assertStringContainsString('attachment; filename="post-'.$post->slug.'.pdf"', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_document_defaults_come_from_config(): void
    {
        config(['pdf.format' => 'letter', 'pdf.orientation' => 'landscape']);

        $builder = app(PdfService::class)->document('pdf.post', ['post' => Post::factory()->create()], 'My Report!.pdf');

        $this->assertSame('letter', $builder->format);
        $this->assertSame('landscape', $builder->orientation);
        $this->assertSame('my-report.pdf', $builder->downloadName);
    }

    public function test_user_content_is_escaped_in_the_pdf_html(): void
    {
        $post = Post::factory()->create(['title' => ['en' => '<b>bold</b>'], 'body' => ['en' => '<script>x</script>']]);

        $html = app(PdfService::class)->document('pdf.post', ['post' => $post], 'x')->getHtml();

        $this->assertStringNotContainsString('<script>x</script>', $html);
        $this->assertStringContainsString('&lt;b&gt;bold&lt;/b&gt;', $html);
    }
}
