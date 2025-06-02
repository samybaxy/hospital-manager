<?php

namespace HospitalManager\Database\Seeders;

class HMOSeeder extends Seeder
{
    public function run()
    {
        $this->log("Creating HMOs");
        
        global $wpdb;
        $table = $wpdb->prefix . 'hm_hmos';
        
        $hmos = [
            'NHIS (National Health Insurance Scheme)',
            'Hygeia HMO',
            'Avon Healthcare Limited',
            'Novo Health Africa',
            'Axa Mansard Health',
            'Total Health Trust',
            'Reliance HMO',
            'AIICO Multishield',
            'Liberty Health',
            'ICEA LION Life Assurance',
            'Metropolitan Health',
            'Managed Healthcare Services',
            'Clearline HMO',
            'Healthcare International',
            'Songhai Health Trust',
            'EVES Healthcare'
        ];
        
        $created = 0;
        
        foreach ($hmos as $hmo_name) {
            $result = $wpdb->insert($table, [
                'name' => $hmo_name
            ]);
            
            if ($result) {
                $created++;
            }
        }
        
        $this->log("Created {$created} HMOs successfully");
    }
}
