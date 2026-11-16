<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
final class ServiceCatalogueSeeder extends Seeder
{
    public function run(): void
    {
        // Inactive templates only. Each actual denomination/bundle must have its own configured service,
        // provider product mapping and immutable pricing rule before it can be activated.
        $templates = [
            ['service_code' => 'airtime_template', 'category' => 'airtime', 'name' => 'Airtime service template', 'validation_schema' => ['network' => 'required|string|in:MTN,AIRTEL,GLO,9MOBILE', 'phone' => 'required|string|regex:/^0[789][01][0-9]{8}$/']],
            ['service_code' => 'data_template', 'category' => 'data', 'name' => 'Data bundle service template', 'validation_schema' => ['network' => 'required|string|in:MTN,AIRTEL,GLO,9MOBILE', 'phone' => 'required|string|regex:/^0[789][01][0-9]{8}$/']],
        ];
        foreach ($templates as $service) {
            DB::table('service_catalogue')->updateOrInsert(['service_code' => $service['service_code']], [
                'category' => $service['category'], 'name' => $service['name'], 'status' => 'inactive',
                'validation_schema' => json_encode($service['validation_schema']),
                'metadata' => json_encode(['template_only' => true, 'activation_requires_supplier_product_mapping_and_pricing' => true]),
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }
}
