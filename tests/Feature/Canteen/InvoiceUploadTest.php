<?php

namespace Tests\Feature\Canteen;

use App\Models\ActivityLog;
use App\Models\Purchase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\SeedsCanteenBasics;
use Tests\TestCase;

class InvoiceUploadTest extends TestCase
{
    use RefreshDatabase, SeedsCanteenBasics;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seedCanteenBasics();
    }

    private function purchaseWithImage(): Purchase
    {
        $vendor = $this->makeVendor('Samosay Supplier', 0);

        $this->actingAs($this->staff)->post('/purchases', [
            'vendor_id' => $vendor->id,
            'bill_amount' => '2200',
            'bill_image' => UploadedFile::fake()->image('bill.jpg', 800, 600),
        ])->assertRedirect(route('dashboard'));

        return Purchase::sole();
    }

    public function test_bill_photo_is_stored_privately_and_flagged_on_the_activity(): void
    {
        $purchase = $this->purchaseWithImage();

        $this->assertNotNull($purchase->bill_image_path);
        $this->assertStringStartsWith('invoices/', $purchase->bill_image_path);
        Storage::disk('local')->assertExists($purchase->bill_image_path);
        $this->assertTrue(ActivityLog::where('reference_type', 'purchase')->sole()->has_image);
    }

    public function test_authenticated_user_can_view_the_invoice(): void
    {
        $purchase = $this->purchaseWithImage();

        $response = $this->actingAs($this->staff)->get(route('invoices.show', $purchase));

        $response->assertOk();
        $this->assertStringContainsString('image/', (string) $response->headers->get('Content-Type'));
    }

    public function test_guest_cannot_view_the_invoice(): void
    {
        $purchase = $this->purchaseWithImage();
        auth()->logout();

        $this->get(route('invoices.show', $purchase))->assertRedirect(route('login'));
    }

    public function test_purchase_without_a_photo_gives_404_on_the_invoice_route(): void
    {
        $vendor = $this->makeVendor('Pepsi', 0);
        $this->actingAs($this->staff)->post('/purchases', ['vendor_id' => $vendor->id, 'bill_amount' => '100'])->assertRedirect();

        $this->actingAs($this->staff)->get(route('invoices.show', Purchase::sole()))->assertNotFound();
    }

    public function test_non_image_upload_is_rejected_and_nothing_is_saved(): void
    {
        $vendor = $this->makeVendor('Pepsi', 0);

        $this->actingAs($this->staff)->post('/purchases', [
            'vendor_id' => $vendor->id,
            'bill_amount' => '100',
            'bill_image' => UploadedFile::fake()->create('bill.pdf', 50, 'application/pdf'),
        ])->assertSessionHasErrors('bill_image');

        $this->assertSame(0, Purchase::count());
        $this->assertMoney(0, $this->vendorBalance($vendor));
    }

    public function test_photo_can_be_replaced_and_the_old_file_is_removed(): void
    {
        $purchase = $this->purchaseWithImage();
        $old = $purchase->bill_image_path;

        $this->actingAs($this->staff)->post(route('invoices.replace', $purchase), [
            'bill_image' => UploadedFile::fake()->image('new-bill.png'),
        ])->assertRedirect();

        $purchase->refresh();
        $this->assertNotSame($old, $purchase->bill_image_path);
        Storage::disk('local')->assertMissing($old);
        Storage::disk('local')->assertExists($purchase->bill_image_path);
    }
}
