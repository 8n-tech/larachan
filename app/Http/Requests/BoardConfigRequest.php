<?php

namespace App\Http\Requests;

use App\Board;
use App\OptionGroup;
use App\Services\UserManager;
use Sabberworm\CSS\OutputFormat;
use Sabberworm\CSS\Parser;
use Sabberworm\CSS\Parsing\UnexpectedTokenException;

class BoardConfigRequest extends Request
{
    /**
     * Input items that should not be returned when reloading the page.
     *
     * @var array
     */
    protected $dontFlash = ['password', 'password_confirmation', 'captcha'];

    /**
     * A list of applicable board option groups.
     *
     * @var App\OptionGroup
     */
    protected $boardOptionGroups;

    /**
     * The board pertinent to the request.
     *
     * @var App\Board
     */
    public $board;

    /**
     * The user.
     *
     * @var App\Trait\PermissionUser
     */
    protected $user;

    /**
     * Fetches the user and our board config.
     */
    public function __construct(Board $board, UserManager $manager)
    {
        $this->user = $manager->user;
        $this->board = $board;
    }

    /**
     * Get all form input.
     *
     * @return array
     */
    public function all($keys = NULL)
    {
        $input = parent::all($keys);

        foreach ($this->getBoardOptions() as $optionGroup) {
            foreach ($optionGroup->options as $option) {
                if (isset($input[$option->option_name])) {
                    $input[$option->option_name] = $option->getSanitaryInput($input[$option->option_name]);
                }
            }
        }

        return $input;
    }

    /**
     * Supplies validation rules for this request.
     *
     * @return array
     */
    public function rules()
    {

        $rules = [
            'boardBasicTitle' => 'required|string|between:1,255',
            'boardBasicDesc' => 'string|between:1,255',
            'boardBasicIndexed' => 'boolean',
            'boardBasicWorksafe' => 'boolean',
        ];

        foreach ($this->getBoardOptions() as $optionGroup) {
            foreach ($optionGroup->options as $option) {
                $rules = array_merge($rules, $option->getValidation());
            }
        }

        return $rules;
    }

    /**
     * Sanitizes input.
     *
     * @return array
     */
    public function sanitize()
    {
        $input = $this->input('boardCustomCSS', false);

        if ($input) {
            $parser = new Parser($input);
            $style = $parser->parse()->render(OutputFormat::createPretty());

            $this->merge(['boardCustomCSS' => $input]);
        }
    }

    /**
     * Determines if the client has access to this form.
     *
     * @return bool
     */
    public function authorize()
    {
        return $this->board->canEditConfig($this->user);
    }

    /**
     * @return array
     */
    public function getBoardOptions()
    {
        if (!isset($this->boardOptionGroups)) {
            $this->boardOptionGroups = OptionGroup::getBoardConfig($this->board);
        }

        return $this->boardOptionGroups;
    }

    /**
     * Validates the request.
     *
     * @return void
     */
    public function validate()
    {
        $validator = $this->getValidatorInstance();
        $messages = $validator->errors();

        try {
            $this->sanitize();
        }
        catch (UnexpectedTokenException $e) {
            $messages->add('boardCustomCSS', "Invalid CSS supplied. Unexpected token.");
        }

        return parent::validate();
    }
}
