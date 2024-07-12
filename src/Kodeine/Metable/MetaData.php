<?php

namespace Kodeine\Metable;

use DateTime;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $type
 */
class MetaData extends Model
{
	/**
	 * @var array
	 */
	protected $fillable = ['key', 'value'];
	
	/**
	 * @var string 
	 */
	private $valueColumn = 'value';
	
	/**
	 * Set up the fillable array, and the value for saving
	 * 
	 * @param Model $parent
	 * @return void
	 */
	public function init(Model $parent): void
	{
		$this->fillable = [$parent->getMetaKeyColumnName(), $parent->getMetaValueColumnName()];
		$this->valueColumn = $parent->getMetaValueColumnName();
	}
	
	/**
	 * @var array
	 */
	protected $dataTypes = ['boolean', 'integer', 'double', 'float', 'string', 'NULL'];
	
	/**
	 * Whether or not to delete the Data on save.
	 *
	 * @var bool
	 */
	protected $markForDeletion = false;
	
	protected $modelCache = [];
	
	/**
	 * Whether or not to delete the Data on save.
	 *
	 * @param bool $bool
	 */
	public function markForDeletion(bool $bool = true) {
		$this->markForDeletion = $bool;
	}
	
	/**
	 * Check if the model needs to be deleted.
	 *
	 * @return bool
	 */
	public function isMarkedForDeletion(): bool {
		return $this->markForDeletion;
	}
	
	/**
	 * Set the value and type.
	 *
	 * @param $value
	 */
	public function setValueAttribute($value) {
		$type = gettype( $value );
		
		if ( is_array( $value ) ) {
			$this->type = 'array';
			$this->attributes[$this->valueColumn] = json_encode( $value );
		}
		elseif ( $value instanceof DateTime ) {
			$this->type = 'datetime';
			$this->attributes[$this->valueColumn] = $this->fromDateTime( $value );
		}
		elseif ( $value instanceof Model ) {
			$this->type = 'model';
			$class = get_class( $value );
			$this->attributes[$this->valueColumn] = $class . (! $value->exists ? '' : '#' . $value->getKey());
			// Update the cache
			$this->modelCache[$class][$value->getKey()] = $value;
		}
		elseif ( is_object( $value ) ) {
			$this->type = 'object';
			$this->attributes[$this->valueColumn] = json_encode( $value );
		}
		else {
			$this->type = in_array( $type, $this->dataTypes ) ? $type : 'string';
			$this->attributes[$this->valueColumn] = $value;
		}
	}
	
	public function getValueAttribute($value) {
		$type = $this->type ?: 'null';
		
		switch ($type) {
			case 'array':
				return json_decode( $value, true );
			case 'object':
				return json_decode( $value );
			case 'datetime':
				return $this->asDateTime( $value );
			case 'model':
			{
				if ( strpos( $value, '#' ) === false ) {
					return new $value();
				}
				
				list( $class, $id ) = explode( '#', $value );
				
				return $this->resolveModelInstance( $class, $id );
			}
		}
		
		if ( in_array( $type, $this->dataTypes ) ) {
			settype( $value, $type );
		}
		
		return $value;
	}
	
	protected function resolveModelInstance($model, $Key) {
		if ( ! isset( $this->modelCache[$model] ) ) {
			$this->modelCache[$model] = [];
		}
		if ( ! isset( $this->modelCache[$model][$Key] ) ) {
			$this->modelCache[$model][$Key] = (new $model())->findOrFail( $Key );
		}
		return $this->modelCache[$model][$Key];
	}
}
