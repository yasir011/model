<?php namespace Yasir011\Model;

use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;

class Builder extends EloquentBuilder {

	/**
	 * Adds a whereIn, whereNotIn or whereNull $primaryKey clause to the query.
	 * This clause is built by querying which entries in the table have a relationship with the specified key
	 * that satisfies the constraint.
	 *
	 * @param  string  $relationshipKey The key used to define the relationship in the model.
	 * @param  string  $column
	 * @param  string  $operator
	 * @param  mixed   $value
	 * @param  string  $boolean
	 * @return \Yasir011\Model\Builder
	 * @throws \Yasir011\Model\BadMethodCallException
	 * @throws \Yasir011\Model\InvalidModelRelationException
	 */
	public function whereRelated($relationshipKey, $column = null, $operator = null, $value = null, $boolean = 'and', $not = false)
	{
		if (is_callable($relationshipKey)) {
			return $this->whereRelatedNested($relationshipKey, $boolean);
		}

		$relationshipKeys = explode('.', $relationshipKey);

		if ( ! count($relationshipKeys) ) {
			throw new InvalidModelRelationException($relationshipKey);
		}

		$parentModel  = $this->model;
		$parentTable 	= $parentModel->getTable();
		$parentKey 		= $parentModel->getKeyName();

		// Initialize the relatedQuery if it hasn't been done already:
		$relatedQuery = DB::table($parentTable)->select("$parentTable.$parentKey");

		// Join with related tables:
		foreach($relationshipKeys as $relationshipKey) {
			$relation 			= $parentModel->$relationshipKey();
			$relatedQuery 	= $this->joinRelated($relatedQuery, $relation);
			$parentModel 		= $relation->getRelated();
		}

		$relatedTable = $parentModel->getTable();

		// Apply where condition:
		$relatedQuery->where("$relatedTable.$column", $operator, $value);

		// List ids and, and translate the query to a whereIn. This should only be done once.
		$pks = $relatedQuery->lists($parentKey);

		if (empty($pks)) {
			return $not ? $this : $this->whereNull($parentKey, $boolean);
		}
		return $not ? $this->whereNotIn($parentKey, $pks, $boolean) : $this->whereIn($parentKey, $pks, $boolean);
	}

	/**
	 *	Adds a join clause to the current relatedQuery using the relation Object.
	 *
	 *	@param \Illuminate\Database\Query 											$query
	 *	@param \Illuminate\Database\Eloquent\Relations\Relation $relation
	 *	@return \Illuminate\Database\Querys
	 */
	protected function joinRelated($query, $relation)
	{
		$parentTable 	= $relation->getParent()->getTable();
		$parentKey		= $relation->getParent()->getKeyName();
		$relatedTable = $relation->getRelated()->getTable();
		$fk 					= $relation->getForeignKey();
		$relationType = str_replace('Illuminate\\Database\\Eloquent\\Relations\\', '', get_class($relation));

		switch($relationType) {
			case 'BelongsTo':
				$query->join($relatedTable, "$relatedTable.$parentKey", '=', "$parentTable.$fk");
				break;
			case 'HasOne': case 'HasMany': case 'MorphOne': case 'MorphMany':
				$query->join($relatedTable, "$parentTable.$parentKey", '=', "$fk");
				break;
			case 'BelongsToMany':
				$table = $relation->getTable();
				$otherKey = $relation->getOtherKey();
				$query->join($table, "$parentTable.$parentKey", '=', "$fk")
					->join($relatedTable, "$relatedTable.$parentKey", '=', "$otherKey");
				break;
			default:
				break;
		}

		return $query;
	}

	/**
	 * Same as whereRelated but with boolean OR applied.
	 *
	 * @param  string  $relationshipKey
	 * @param  string  $column
	 * @param  string  $operator
	 * @param  mixed   $value
	 * @param  string  $boolean
	 * @return \Yasir011\Model\Builder
	 */
	public function orWhereRelated($relationshipKey, $column = null, $operator = null, $value = null)
	{
		return $this->whereRelated($relationshipKey, $column, $operator, $value, 'or');
	}

	/**
	 * Same as whereRelated but when trying to find records that are NOT related.
	 *
	 * @param  string  $relationshipKey
	 * @param  string  $column
	 * @param  string  $operator
	 * @param  mixed   $value
	 * @param  string  $boolean
	 * @return \Yasir011\Model\Builder
	 */
	public function whereNotRelated($relationshipKey, $column = null, $operator = null, $value = null, $boolean = 'and')
	{
		return $this->whereRelated($relationshipKey, $column, $operator, $value, $boolean, true);
	}

	/**
	 * Same as whereNotRelated but with boolean OR applied.
	 *
	 * @param  string  $relationshipKey
	 * @param  string  $column
	 * @param  string  $operator
	 * @param  mixed   $value
	 * @param  string  $boolean
	 * @return \Yasir011\Model\Builder
	 */
	public function orWhereNotRelated($relationshipKey, $column = null, $operator = null, $value = null)
	{
		return $this->whereNotRelated($relationshipKey, $column, $operator, $value, 'or');
	}

	/**
	 * Add a nested where statement to the query.
	 *
	 * @param  \Closure $callback
	 * @param  string   $boolean
	 * @return \Yasir011\Model\Builder
	 */
	public function whereRelatedNested(Closure $callback, $boolean = 'and')
	{
		// To handle nested queries we'll actually create a brand new query instance
		// and pass it off to the Closure that we have. The Closure can simply do
		// do whatever it wants to a query then we will store it for compiling.
		$type = 'Nested';

		$builder = new Builder($this->query->newQuery());
		$builder->setModel($this->model);

		call_user_func($callback, $builder);

		// Once we have let the Closure do its things, we can gather the bindings on
		// the nested query builder and merge them into these bindings since they
		// need to get extracted out of the children and assigned to the array.
		$query = $builder->getQuery();
		if (count($query->wheres))
		{
			$this->getQuery()->wheres[] = compact('type', 'query', 'boolean');

			$this->getQuery()->mergeBindings($query);
		}

		return $this;
	}
}