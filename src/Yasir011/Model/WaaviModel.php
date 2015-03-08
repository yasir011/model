<?php namespace Yasir011\Model;

use Illuminate\Support\MessageBag;
use Illuminate\Database\Eloquent\Model as Eloquent;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Input;

class WaaviModel extends Eloquent {

	/**
   * The validation rules.
   *
   * @var array
   */
  protected $rules = array();

  /**
   * The array of custom error messages.
   *
   * @var array
   */
  protected $customMessages = array();

  /**
   * The message bag instance containing validation error messages
   *
   * @var \Illuminate\Support\MessageBag
   */
  protected $validationErrors;

  /**
   * Create a new WaaviModel model instance.
   *
   * @param array $attributes
   * @return \Yasir011\Model\WaaviModel
   */
  public function __construct(array $attributes = array()) {
    parent::__construct($attributes);
    $this->validationErrors = new MessageBag;
  }

	/**
	 * Get the messages for the instance.
	 *
	 * @return \Illuminate\Support\MessageBag
	 */
	public function errors()
	{
    return $this->validationErrors;
	}

	/**
	 * Get the messages for the instance.
	 *
	 * @return \Yasir011\Model\WaaviModel
	 */
	public function addError($key, $value)
	{
		$this->validationErrors->add($key, $value);
		return $this;
	}

	/**
   * Cleans the rules array, deleting empty rules and qualifying the unique constraint.
   *
   * @return bool
   */
	protected function cleanRules()
	{
		foreach ($this->rules as $field => &$ruleset) {
			// Remove empty rules
			if ( empty($ruleset) ) {
				unset($this->rules[$field]);
			}
			// Seek unique validation rules so that when updating a model the constraint is not applied to itself
			elseif( $this->id ) {
				$ruleset = explode('|', $ruleset);
	      foreach ($ruleset as &$rule) {
	        if (strpos($rule, 'unique') === 0) {
	          $params = explode(',', $rule);
	          $params[1] = array_get($params, 1, $field);
	          $params[2] = $this->id;
	          $rule = implode(',', $params);
	        }
	      }
	      $ruleset = implode('|', $ruleset);
			}
    }
	}

	/**
   * Validate the model instance
   *
   * @return bool
   */
	public function isValid()
	{
		// Clean the ruleset
		$this->cleanRules();

		// Validate the model:
		$attributes = $this->getAttributes();
		$validator 	= Validator::make($attributes, $this->rules, $this->customMessages);
		$valid   		= $validator->passes();

		// if the model is valid, unset old errors
		if ($valid) {
			$this->validationErrors = new MessageBag;
		} else {
			$this->validationErrors = $validator->messages();
		}

		return $valid;
	}

	/**
	 * Save the model to the database.
	 *
	 * @param  array  $options
	 * @return bool
	 */
	public function save(array $options = array())
	{
		if ( ! $this->isValid() ) {
			return false;
		}
		return parent::save($options);
	}

	/**
	 * Save the model to the database without validation.
	 *
	 * @param  array  $options
	 * @return bool
	 */
	public function forceSave(array $options = array())
	{
		return parent::save($options);
	}

	/**
	 *	Get the model's validation rules
	 *
	 *	@return array
	 */
	public function getRules()
	{
		return $this->rules;
	}

	/**
	 *	Set the model's validation rules.
	 *
	 *	@param 	array 	$rules
	 *	@return \Yasir011\Model\WaaviModel
	 */
	public function setRules($rules)
	{
		$this->rules = $rules;
		return $this;
	}

	/**
	 *	Adds or overwrites a validation rule.
	 *
	 *	@param 	string 	$field
	 *	@param 	string 	$ruleset
	 *	@return \Yasir011\Model\WaaviModel
	 */
	public function setRule($field, $ruleset)
	{
		$this->rules[$field] = $ruleset;
		return $this;
	}

	/**
	 *	Removes a validation rule.
	 *
	 *	@param 	string 	$field
	 *	@return \Yasir011\Model\WaaviModel
	 */
	public function removeRule($field)
	{
		unset($this->rules[$field]);
		return $this;
	}

	/**
	 *	Get the model's validation custom messages.
	 *
	 *	@return array
	 */
	public function getCustomMessages()
	{
		return $this->customMessages;
	}

	/**
	 *	Set the model's validation custom messages.
	 *
	 *	@param 	array 	$customMessages
	 *	@return \Yasir011\Model\WaaviModel
	 */
	public function setCustomMessages($customMessages)
	{
		$this->customMessages = $customMessages;
		return $this;
	}

	/**
	 *	Adds or overwrites a validation custom message.
	 *
	 *	@param 	string 	$rule
	 *	@param 	string 	$message
	 *	@return \Yasir011\Model\WaaviModel
	 */
	public function setCustomMessage($rule, $message)
	{
		$this->customMessages[$rule] = $message;
		return $this;
	}

	/**
	 *	Removes a validation custom message.
	 *
	 *	@param 	string 	$rule
	 *	@return \Yasir011\Model\WaaviModel
	 */
	public function removeCustomMessage($rule)
	{
		unset($this->customMessages[$rule]);
		return $this;
	}

	/**
	 * Get a new query builder for the model's table.
	 * Overriden from {@link \Model\Eloquent} to allow for filtering on related models attributes.
	 *
	 * @param  bool  $excludeDeleted
	 * @return \Illuminate\Database\Eloquent\Builder
	 */
	public function newQuery($excludeDeleted = true) {
		$builder = new Builder($this->newBaseQueryBuilder());

		// Once we have the query builders, we will set the model instances so the
		// builder can easily access any information it may need from the model
		// while it is constructing and executing various queries against it.
		$builder->setModel($this)->with($this->with);

		if ($excludeDeleted and $this->softDelete) {
			$builder->whereNull($this->getQualifiedDeletedAtColumn());
		}

		return $builder;
	}

}