<?php
namespace PatrykNamyslak\FormBuilder;

class Input{
    private(set) InputType $type;
    private ?string $label = null;
    /**
     * The type that is used for the column i.e enum, text or varchar
     * @var string
     */
    private string $columnTypeInString;
    private string $name;
    private ?string $defaultValue = null;
    private int $maxLength = 255;
    private ?array $defaultValues = null;
    private bool $acceptMultipleValues = false;
    private bool $required = false;
    private bool $json = false;
    private string $dataTypeExpectedByDatabase;



    private const PLACEHOLDER_TEXT_FOR_JSON_FIELD = "Seperate using commas E.g One,two,three";

    // Chainable methods
    public function default(string|null $value): static{
        $this->defaultValue = $value;
        return $this;
    }

    public function json(){
        $this->json = true;
        return $this;
    }
    public function dataTypeExpectedByDatabase(string $value): static{
        $this->dataTypeExpectedByDatabase = $value;
        return $this;
    }
    /**
     * Set the default values from a string
     * @param string $stringifiedValues This is usually mixed in with the type part of a columns structure i.e enum('1','2','3','4')
     */
    public function values(string $stringifiedValues): static{
        // Find where the values start
        $openingBracket = strpos(haystack: $stringifiedValues, needle: "(");
        $defaultValues = substr(string: $stringifiedValues, offset: $openingBracket);
        $defaultValues = trim(string: $defaultValues, characters: "()");
        $defaultValues = explode(separator: ",", string: $defaultValues);

        // Remove single quotes
        foreach($defaultValues as &$value){
            $value = trim($value, "'");
        }
        $this->defaultValues = $defaultValues;
        return $this;
    }

    public function length(int $value){
        $this->maxLength = $value;
        return $this;
    }

    /**
     * Sets the name of the input field
     * @param string $value Name of the column that the input field is being generated for
     * @return static
     */
    public function name(string $value){
        $this->name = $value;
        return $this;
    }

    /**
     * Sets the input fields label, either uses a given label name or the name of the field
     * @param ?string $value
     * @return static
     */
    public function label(?string $value = null){
        $this->label = str_replace("_", " ", $value ?? $this->name);
        return $this;
    }
    public function required(bool $isRequired = true){
        if ($isRequired){
            $this->required = true;
        }
        return $this;
    }

    public function type(string $type){
        // extract the type by trimming the values. i.e enum(1,2,3,4) will be just enum. $values is (1,2,3,4) so we are using an intersect method to remove it.
        if(str_contains($type, "(")){
            $type = strtolower(
                string: substr(
                    string: $type,
                    offset: 0,
                    length: strpos(haystack: $type, needle: "(")
                    )
                );
        }
        $this->columnTypeInString = $type;
        if (($this->columnTypeInString === "enum") and (count($this->defaultValues) === 2)){
            $this->type = InputType::RADIO;
        }else{
            // Check if it is meant to be a password field
            if (str_contains($this->name, "password")){
                $this->type = InputType::PASSWORD;
                return $this;
            }
            $this->type = match($type){
                "int", "smallint", "mediumint", "bigint" => InputType::NUMBER,
                "varchar", "json" => InputType::TEXT,
                "text", "longtext" => InputType::TEXT_AREA,
                "enum" => InputType::DROPDOWN,
                "boolean", "bool", "tinyint" => InputType::RADIO,
            };
        }

        return $this;
    }

    public function acceptMultipleValues(){
        $this->acceptMultipleValues = true;
        return $this;
    }

    // Non chainable methods, these must be at the end of the chain!
    /**
     * Renders the label
     */
    function renderLabel(): static{
        if ($this->label){
            ?>
            <label for="<?= $this->name ?>"><?= ucwords($this->label) ?>:</label>
            <?php
        }
        return $this;
    }

    /**
     * Returns a textarea html element while also respecting database maximums
     */
    public function textArea(?string $placeholder = NULL){
        $placeholder = $this->createInputPlaceholder($placeholder);
        ?>
        <textarea 
        name="<?= $this->name ?>" 
        placeholder="<?= $placeholder ?>"
        <?= $this->renderRequiredAttribute() ?> 
        maxlength="<?= $this->maxLength ?>" 
        value="<?= $this->defaultValue ?>"
        ></textarea>
        <?php
    }

    public function passwordField(?string $placeholder){
        $placeholder = $this->createInputPlaceholder($placeholder);
        ?>
        <input type="password" name="<?= $this->name ?>" 
        placeholder="<?= $placeholder ?>" 
        <?php
        $this->renderRequiredAttribute();
        ?>
        >
        <?php
    }

    public function dropdown(): void{
        ?>
        <select name="<?= $this->name ?>" 
        <?php $this->renderRequiredAttribute(); ?>
        >
        <?php
        foreach($this->defaultValues as $defaultValue):
            ?>
            <option value="<?= $defaultValue ?>"><?= $this->prettyPrint($defaultValue) ?></option>
            <?php
        endforeach;
        ?>
        </select>
        <?php
    }

    public function radio(){
        foreach($this->defaultValues as $option): ?>
            <div>
                <span><?= $option ?></span>
                <input 
                type="radio" 
                name="<?= $this->name ?>" 
                value="<?= $option ?>">
            </div>
        <?php
        endforeach;
    }

    public function textField(?string $placeholder = null): void{
        $placeholder = $this->createInputPlaceholder($placeholder);
        ?>
        <input 
        type="text" 
        placeholder="<?= $placeholder ?>"
        name="<?= $this->name ?>" 
        maxlength="<?= $this->maxLength ?>" 
        <?php $this->renderMultipleAttribute() ?>
        <?php $this->renderRequiredAttribute() ?>
        >
        <?php
    }

    /**
     * This functions pure purpose is to check if there should be a required mark on an input field or not include it
     * @return void
     */
    public function renderRequiredAttribute(): void{
        if($this->required){
            echo "required";
        }
    }

    public function renderMultipleAttribute(): void{
        if($this->acceptMultipleValues){
            echo "multiple";
        }
    }

    public function getColumnTypeInString(): string{
        return $this->columnTypeInString;
    }

    private function createInputPlaceholder(?string $placeholder = NULL): string{
        return match(true){
            // if the field is a json field append a guide for data insertion (Show what is expected)
            $this->json => ($placeholder ?? $this->prettyPrint($this->name)) . ": " . self::PLACEHOLDER_TEXT_FOR_JSON_FIELD,
            isset($placeholder) => $placeholder,
            default => ucfirst(str_replace("_", " ", $this->name)),
        };
    }

    /**
     * Makes the text look pretty :P
     * @param string $text
     * @return string
     */
    public function prettyPrint(string $text): string{
        $text = str_replace(["_"], " ", $text);
        return ucwords($text);
    }
}