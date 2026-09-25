<?php

namespace Tests\Feature;

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CustomerImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_does_not_save_and_import_skips_invalid_or_duplicate_rows(): void
    {
        Storage::fake('local');

        Customer::create([
            'first_name' => 'Existing', 'last_name' => 'Person',
            'email' => 'taken@example.com', 'country' => 'Lithuania',
        ]);

        $csv = implode("\n", [
            'first_name,last_name,email,phone,birth_date,country',
            'Aistė,Petrauskaitė,AISTE@example.com,,1994-04-18,Lithuania',
            'Jonas,Kazlauskas,taken@example.com,,,Lithuania',
            'Marta,Nowak,invalid-email,,,Poland',
            'Ona,Jankauskaitė,aiste@example.com,,,Lithuania',
            'Eva,Smith,eva@example.com,,2999-01-01,United Kingdom',
        ]);

        $this->post(route('imports.preview'), [
            'file' => UploadedFile::fake()->createWithContent('customers.csv', $csv),
        ])->assertRedirect(route('home'));

        $this->assertDatabaseCount('customers', 1);

        $this->get(route('home'))->assertInertia(fn (Assert $page) => $page
            ->component('Import/Index')
            ->where('preview.total', 5)
            ->where('preview.valid', 1)
            ->where('preview.failed', 4)
            ->has('preview.rows', 5)
            ->etc());

        $this->post(route('imports.store'))
            ->assertRedirect(route('home'))
            ->assertSessionHas('summary', [
                'total' => 5, 'imported' => 1, 'failed' => 4,
            ]);

        $this->assertDatabaseCount('customers', 2);
        $this->assertDatabaseHas('customers', ['email' => 'aiste@example.com']);
        $this->assertDatabaseMissing('customers', ['email' => 'invalid-email']);
    }

    public function test_invalid_header_is_rejected_without_staging_a_file(): void
    {
        Storage::fake('local');

        $this->post(route('imports.preview'), [
            'file' => UploadedFile::fake()->createWithContent('bad.csv', "name,email\nAistė,aiste@example.com"),
        ])->assertSessionHasErrors('file');

        $this->assertEmpty(Storage::disk('local')->allFiles('customer-imports'));
        $this->assertDatabaseCount('customers', 0);
    }

    public function test_valid_row_can_use_email_from_an_earlier_invalid_row(): void
    {
        Storage::fake('local');

        $csv = implode("\n", [
            'first_name,last_name,email,phone,birth_date,country',
            ',Wrong,reuse@example.com,,,Lithuania',
            'Right,Person,reuse@example.com,,,Lithuania',
        ]);

        $this->post(route('imports.preview'), [
            'file' => UploadedFile::fake()->createWithContent('reuse.csv', $csv),
        ])->assertRedirect(route('home'));

        $this->get(route('home'))->assertInertia(fn (Assert $page) => $page
            ->component('Import/Index')
            ->where('preview.valid', 1)
            ->where('preview.failed', 1)
            ->etc());

        $this->post(route('imports.store'))->assertSessionHas('summary', [
            'total' => 2, 'imported' => 1, 'failed' => 1,
        ]);

        $this->assertDatabaseHas('customers', ['first_name' => 'Right', 'email' => 'reuse@example.com']);
    }

    public function test_import_requires_a_staged_preview(): void
    {
        $this->post(route('imports.store'))->assertSessionHasErrors('file');

        $this->assertDatabaseCount('customers', 0);
    }
}
