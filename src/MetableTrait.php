<?php namespace vendocrat\Meta;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as BaseCollection;

trait MetableTrait
{
	/**
	 * @var Collection
	 */
	protected $meta;

	/**
	 * True when the meta is loaded
	 *
	 * @var
	 */
	protected $metaLoaded = false;

	/**
	 * Get all meta data for this model.
	 *
	 * @return \Illuminate\Database\Eloquent\Relations\MorphMany
	 */
	public function meta()
	{
		return $this->morphMany('vendocrat\Meta\Models\Meta', 'metable');
	}

	/**
	 * Gets meta data
	 *
	 * @param string $key
	 * @param null|mixed $default
	 * @param bool $raw
	 * @return Collection
	 */
	public function getMeta( $key, $default = null, $raw = false )
	{
		$meta = $this->meta()
			->where('key', $key)
			->get();

		if ( $raw ) {
			$collection = $meta;
		} else {
			$collection = new Collection();

			foreach ( $meta as $m ) {
				$collection->put( $m->id, $m->value );
			}
		}

		if ( 0 == $collection->count() ) {
			return $default;
		}

		return $collection->count() <= 1 ? $collection->first() : $collection;
	}

	/**
	 * Gets all meta data
	 *
	 * @return \Illuminate\Support\Collection
	 */
	public function getAllMeta()
	{
		return new Collection($this->meta->lists('value', 'key'));
	}

	/**
	 * Adds meta data
	 *
	 * @return mixed
	 */
	public function setMeta( $key, $value )
	{
		return $this->updateMeta( $key, $value );
		/*
		$existing = $this->meta()
			->where('key', $key)
			->where('value', MetableUtils::maybe_serialize($value))
			->first();

		if ( $existing ) {
			return false;
		}

		return $meta = $this->meta()->find([
			'key'   => $key,
			'value' => $value,
		]);
		*/
	}

	/**
	 * Appends a value to an existing meta entry
	 * Resets all keys
	 *
	 * @return mixed
	 */
	public function appendMeta( $key, $value )
	{
		$meta = $this->getMeta($key);

		if ( ! $meta ) {
			$meta = [];
		} elseif ( ! is_array($meta) ) {
			$meta = [$meta];
		}

		if ( is_array($value) ) {
			$meta = array_merge($meta, $value);
		} else {
			$meta[] = $value;
		}

		return $this->updateMeta( $key, array_values( array_unique($meta) )) ;
	}

	/**
	 * Updates meta data
	 *
	 * @return mixed
	 */
	public function updateMeta( $key, $newValue, $oldValue = false )
	{
		$meta = $this->getMeta($key, null, true);

		if ( $meta == null ) {
			return $this->addMeta($key, $newValue);
		}

		$obj = $this->getEditableItem($meta, $oldValue);

		if ( $obj !== false ) {
			$isSaved = $obj->update([
				'value' => $newValue
			]);

			return $isSaved ? $obj : $obj->getErrors();
		}

		return null;
	}

	/**
	 * Deletes meta data
	 *
	 * @param $key
	 * @param bool $value
	 * @return mixed
	 */
	public function deleteMeta( $key, $value = false )
	{
		if ($value) {
			$meta = $this->getMeta($key, null, true);
			if ($meta == null) {
				return false;
			}
			$obj = $this->getEditableItem($meta, $value);
			return $obj !== false ? $obj->delete() : false;
		} else {
			return $this->meta()->where('key', $key)->delete();
		}
	}

	/**
	 * Deletes all meta data
	 *
	 * @return mixed
	 */
	public function deleteAllMeta()
	{
		return $this->meta()->delete();
	}

	/**
	 * Gets an item to edit
	 *
	 * @return mixed
	 */
	protected function getEditableItem( $meta, $value )
	{
		if ( $meta instanceof Collection ) {
			if ( $value === false ) {
				return false;
			}

			$filtered = $meta->filter(function($m) use ($value) {
				return $m->value == $value;
			});

			$obj = $filtered->first();

			if ( $obj == null ) {
				return false;
			}
		} else {
			$obj = $meta;
		}

		return $obj->exists ? $obj : false;
	}

	/**
	 * Check if model has meta data by key
	 *
	 * @param $key
	 * @return bool
	 */
	public function hasMeta( $key )
	{
		if ( $meta = $this->meta()->where('key', $key)->first() ) {
			return true;
		}

		return false;
	}

	/**
	 * @param $query
	 * @param $key
	 * @param $value
	 * @return mixed
	 */
	public function scopeMeta( $query, $key, $value )
	{
		return $query->whereHas('meta', function($q) use($key, $value) {
			$q->where( 'key', '=', strtolower($key) )
				->where( 'value', '=', $value );
		});
	}
}