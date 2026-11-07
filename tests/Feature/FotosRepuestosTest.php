<?php

/** Foto opcional de los repuestos: al crearlo, al cambiarla en el kárdex y solo para su negocio. */

use App\Models\Repuesto;
use App\Support\Roles;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;

beforeEach(function () {
    Storage::fake('local');
    $this->almacenero = usuarioCon(Roles::ALMACENERO);
    $this->actingAs($this->almacenero);
});

test('se puede crear un repuesto con foto', function () {
    Volt::test('repuestos.index')
        ->set('codigo', 'BAT-12V')
        ->set('nombre', 'Batería 12V')
        ->set('foto', UploadedFile::fake()->image('bateria.png', 1800, 1800))
        ->call('crear')
        ->assertHasNoErrors();

    $repuesto = Repuesto::where('codigo', 'BAT-12V')->sole();
    Storage::disk('local')->assertExists($repuesto->foto_path);
    expect(getimagesizefromstring(Storage::disk('local')->get($repuesto->foto_path))[0])->toBe(1000);

    $this->get(route('repuestos.foto', $repuesto))->assertOk();
    $this->get(route('repuestos.index'))->assertSee(route('repuestos.foto', $repuesto), false);
});

test('sin foto se muestra la silueta y la foto se puede agregar desde el kárdex', function () {
    $repuesto = Repuesto::factory()->for($this->almacenero->negocio)->create();

    $this->get(route('repuestos.ver', $repuesto))->assertSee('Repuesto sin foto')->assertSee('Agregar foto');

    Volt::test('repuestos.ver', ['repuesto' => $repuesto])
        ->set('foto', UploadedFile::fake()->image('filtro.jpg'))
        ->assertHasNoErrors();

    $primera = $repuesto->fresh()->foto_path;
    expect($primera)->not->toBeNull();

    Volt::test('repuestos.ver', ['repuesto' => $repuesto->fresh()])->set('foto', UploadedFile::fake()->image('otra.jpg'));
    Storage::disk('local')->assertMissing($primera); // la anterior se borra al cambiarla
});

test('la foto de un repuesto no la ve otro negocio', function () {
    $repuesto = Repuesto::factory()->for($this->almacenero->negocio)->create();
    Volt::test('repuestos.ver', ['repuesto' => $repuesto])->set('foto', UploadedFile::fake()->image('x.jpg'));

    $this->actingAs(usuarioCon(Roles::DUENO))->get(route('repuestos.foto', $repuesto))->assertNotFound();
});
