<?php

namespace HospitalManager\Models;

class Appointment extends BaseModel
{
    protected $table = 'hm_appointments';

    protected $fillable = [
        'patient_id',
        'doctor_id',
        'appointment_date',
        'appointment_time',
        'status',
        'reason',
        'notes',
        'created_at',
        'updated_at'
    ];

    public function doctor()
    {
        return get_user_by('id', $this->doctor_id);
    }

    public function patient()
    {
        return get_user_by('id', $this->patient_id);
    }
}
