<?php
namespace LibNik\Exception;

class Model extends Generic
{
    protected $data = array();
    protected $errorcodes = array(
        'NO_SCHEMA',
        'CONSTRUCT:ID_MISSING',
        'MODEL_DATA:SET_WHEN_LOCKED',
        'MODEL_DATA:CANNOT_CHANGE_ID',
        'MODEL_DATA:DATETIME_VALUE_EXPECTED_FOR_COLUMN',
        'MODEL_DATA:SCALAR_VALUE_EXPECTED_FOR_COLUMN',
        'MODEL_DATA:MODEL_EXPECTED_FOR_KEY',
        'MODEL_DATA:ARRAY_EXPECTED_FOR_PIVOT',
        'MODEL_DATA:MODEL_EXPECTED_IN_PIVOT_ARRAY',
        'MODEL_DATA:CANNOT_SET_EXTERNAL_KEYS_TO_THIS_TABLE',
        'MODEL_DATA:UNEXPECTED_COLUMN_NAME',
        'MODEL_DATA_UPDATE:PIVOT_INCORRECT_OBJECT_TYPE'
    );

    public function __construct($code, $data = null, \Exception $previous_exception = null)
    {
        $message = $this->make_message($code, $data);
        parent::__construct($code, $message, $data, $previous_exception);
    }
    
    private function make_message($code, $data)
    {
        switch($code) {
            case 'NO_SCHEMA':
                return $code.': Could not find a schema definition for this object. Are you sure the classname and table are the same (case-insensitive). Otherwise, try calling Model::generate_schema(true) to refresh the schema cache.';
            
            case 'CONSTRUCT:ID_MISSING':
                $id = is_object($data) ? 'object' : $data;
                return $code.': You called a Model constructor with an id of "'.$id.'" - we couldnt find this in the database!';
            
            case 'MODEL_DATA:SET_WHEN_LOCKED':
                list($column, $value) = $data;
                return $code.': It appears you tried to assign a new value to the "'.$column.'" column directly on the object: Updates should be done on the Model_Data object ($obj->db()) instead!';
            
            case 'MODEL_DATA:CANNOT_CHANGE_ID':
                return $code.': You cannot change the id column of an object. You probably meant to make a new object instead?';
            
            case 'MODEL_DATA:DATETIME_VALUE_EXPECTED_FOR_COLUMN':
                list($column, $value) = $data;
                return $code.': Column "'.$column.'" has been declared as a Datetime field - you tried to set it to "'.$value.'".';
            
            case 'MODEL_DATA:SCALAR_VALUE_EXPECTED_FOR_COLUMN':
                list($column, $value) = $data;
                $type = gettype($value);
                
                return $code.': Property "'.$column.'" on this object is a database column which can only accept simple variables. You tried to assign a "'.$type.'" to it.';
            
            case 'MODEL_DATA:MODEL_EXPECTED_FOR_KEY':
                list($column, $value) = $data;
                $type = gettype($value);
                
                if ($type == 'integer')
                {
                    return $code.': Property "'.$column.'" expected a Model object, but you gave it an integer - Perhaps you meant to set "'.$column.'_id" instead?';
                }
                return $code.': Property "'.$column.'" expected a Model object, but you gave it a variable of type "'.$type.'"';
                
            case 'MODEL_DATA:ARRAY_EXPECTED_FOR_PIVOT':
                list($column, $value) = $data;
                $type = gettype($value);
                
                if ($type == 'object' and $value instanceof Model)
                {
                    return $code.': Property "'.$column.'" represents a M-M (Pivot) relationship. It expects an array of Model objects, but you gave it a Model object. If you intended to replace all of the objects in this join with a single object, you should wrap your Model object in array first.';
                } 
                return $code.': Property "'.$column.'" represents a M-M (Pivot) relationship. It expects an array of Model objects, but you gave it a variable of type "'.$type.'" instead.';
            
            case 'MODEL_DATA:MODEL_EXPECTED_IN_PIVOT_ARRAY':
                list($column, $value, $obj) = $data;
                $type = gettype($obj);
                return $code.': Property "'.$column.'" represents a M-M (Pivot) relationship. You successfully passed an array to this property, but it was expecting an array of Model objects and you gave it an "'.$type.'"';
            
            case 'MODEL_DATA:CANNOT_SET_EXTERNAL_KEYS_TO_THIS_TABLE':
                list($column, $value) = $data;
                return $code.': Property "'.$column.'" represents a list of objects that have a foreign key that refers to this object. To change this, you must update those objects - you cannot alter this relationship from here.';
            
            case 'MODEL_DATA:UNEXPECTED_COLUMN_NAME':
                list($column, $value, $model) = $data;
                return $code.': Property "'.$column.'" does not exist in the schema for this object. Please check the $model for this object, ot look at $obj->var_dump()';
            
            case 'MODEL_DATA_UPDATE:PIVOT_INCORRECT_OBJECT_TYPE':
                list($value, $table, $pivot) = $data;
                $class = get_class($value);
                return $code.': Trying to update property "'.$table.'" (pivot:.'.$pivot['pivot'].') - Was expecting an array of "'.$pivot['table'].'" objects, but found a "'.$class.'"!';
            
            default:
                return $code.": Unknown code";
        }
    }
}
