<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class QualityControlTest extends TestCase
{
    /**
     * Test Quality Control store endpoint.
     *
     * @return void
     */
    public function test_quality_control_can_be_created()
    {
        $payload = [
            'kode_seri' => 'TEST-QC-001',
            'jumlah_barang_nota' => 100,
            'jumlah_diterima' => 98,
            'items' => [
                [
                    'status' => 'lolos',
                    'sku' => 'SKU-A1',
                    'jumlah' => 90
                ],
                [
                    'status' => 'reject',
                    'sku' => 'SKU-A1-R',
                    'jumlah' => 8
                ]
            ]
        ];

        $response = $this->postJson('api/quality-control', $payload);
        $response->dump();

        $response->assertStatus(201)
                 ->assertJsonStructure([
                     'message',
                     'data' => [
                         'id',
                         'kode_seri',
                         'jumlah_barang_nota',
                         'jumlah_diterima',
                         'items' => [
                             '*' => [
                                 'id',
                                 'status',
                                 'sku',
                                 'jumlah'
                             ]
                         ]
                     ]
                 ]);

        // Clean up
        \App\Models\QualityControl::where('kode_seri', 'TEST-QC-001')->delete();
    }
}
