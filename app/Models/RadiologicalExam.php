<?php
namespace HospitalManager\Models;

use WPMVC\MVC\Traits\FindTrait;

class RadiologicalExam extends BaseModel
{
    use FindTrait;

    protected $primaryKey = 'id';
    protected $tableName = 'hm_radiological_exams';
    protected $fillable = [
        'visitation_id',
        'tech_id',
        'results'
    ];
    
    /**
     * Constructor
     */
    public function __construct(array $attributes = [])
    {
        global $wpdb;
        $this->table = $wpdb->prefix . $this->tableName;
        
        // Ensure both lowercase 'id' and uppercase 'ID' exist for compatibility
        if (isset($attributes['id']) && !isset($attributes['ID'])) {
            $attributes['ID'] = $attributes['id'];
        } elseif (isset($attributes['ID']) && !isset($attributes['id'])) {
            $attributes['id'] = $attributes['ID'];
        }
        
        parent::__construct($attributes);
    }

    /**
     * Find a radiological exam by ID
     * 
     * @param mixed $id Record ID.
     * @return RadiologicalExam|null
     */
    public static function find($id = 0)
    {
        global $wpdb;
        
        if (empty($id)) {
            return null;
        }
        
        // Get the table name
        $instance = new self();
        $table = $instance->getTable();
        
        // Fetch the record directly from the database
        $query = $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id);
        $data = $wpdb->get_row($query, ARRAY_A);
        
        if (!$data) {
            return null;
        }
        
        // Make sure we have both lowercase 'id' and uppercase 'ID' for compatibility
        if (isset($data['id']) && !isset($data['ID'])) {
            $data['ID'] = $data['id'];
        } elseif (isset($data['ID']) && !isset($data['id'])) {
            $data['id'] = $data['ID'];
        }
        
        // Create a new instance with the fetched data
        return new self($data);
    }

    /**
     * Create a new radiological exam record
     *
     * @param array $attributes
     * @return RadiologicalExam
     * @throws \Exception
     */
    public static function create(array $attributes)
    {
        global $wpdb;
        $table = (new static)->getTable();
        
        // Set created_at if applicable
        if (!isset($attributes['created_at'])) {
            $attributes['created_at'] = current_time('mysql');
        }
        
        $result = $wpdb->insert(
            $table,
            $attributes,
            array_map(function($field) {
                return is_numeric($field) ? '%d' : '%s';
            }, $attributes)
        );

        if ($result === false) {
            throw new \Exception($wpdb->last_error);
        }

        $attributes['id'] = $wpdb->insert_id;
        $attributes['ID'] = $attributes['id']; // Add uppercase ID for compatibility
        return new static($attributes);
    }
    
    /**
     * Relationship with visitation
     */
    public function visitation()
    {
        if (!isset($this->attributes['visitation_id'])) {
            return null;
        }
        return \HospitalManager\Models\Visitation::find($this->attributes['visitation_id']);
    }

    /**
     * Relationship with technician (WordPress user)
     */
    public function technician()
    {
        if (!isset($this->attributes['tech_id'])) {
            return null;
        }
        
        // Return the user ID directly since UserModel is abstract
        return get_user_by('id', $this->attributes['tech_id']);
    }
    
    /**
     * Save the model to the database.
     * 
     * @return bool
     */
    public function save()
    {
        global $wpdb;
        
        $table = $this->getTable();
        $data = [];
        
        // Prepare only fillable attributes for saving
        foreach ($this->fillable as $field) {
            if (isset($this->attributes[$field])) {
                $data[$field] = $this->attributes[$field];
            }
        }
        
        // Add updated_at timestamp
        $data['updated_at'] = current_time('mysql');
        
        // Determine if this is an update or insert
        if (isset($this->attributes['id']) && !empty($this->attributes['id'])) {
            // This is an update
            $result = $wpdb->update(
                $table,
                $data,
                ['id' => $this->attributes['id']],
                array_map(function($field) {
                    return is_numeric($field) ? '%d' : '%s';
                }, $data),
                ['%d']
            );
            
            return $result !== false;
        } else {
            // This is an insert
            $result = $wpdb->insert(
                $table,
                $data,
                array_map(function($field) {
                    return is_numeric($field) ? '%d' : '%s';
                }, $data)
            );
            
            if ($result !== false) {
                $this->attributes['id'] = $wpdb->insert_id;
                $this->attributes['ID'] = $this->attributes['id']; // For compatibility
                return true;
            }
            
            return false;
        }
    }
    
    /**
     * Delete the model from the database.
     * 
     * @return bool
     */
    public function delete()
    {
        global $wpdb;
        
        if (!isset($this->attributes['id'])) {
            return false;
        }
        
        $table = $this->getTable();
        $result = $wpdb->delete(
            $table,
            ['id' => $this->attributes['id']],
            ['%d']
        );
        
        return $result !== false;
    }
}
