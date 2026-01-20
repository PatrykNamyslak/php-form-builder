<?php
namespace PatrykNamyslak\FormBuilder;

use Exception;
use PatrykNamyslak\Patbase;

session_start();

class Form{
    /** An array of objects with all of the columns and their structure i.e $tableStructure[0]->Field is the name of the column, reference: ../TableStructureDocumentation.txt*/
    private(set) array $tableStructure;
    private(set) array $fieldNames;
    private string $action;
    private string $method;
    private bool $wrapField = false;
    private(set) bool $htmx = false;
    private(set) ?string $htmxResponseTarget = NULL;
    private(set) ?HtmxSwapMode $htmxSwapMode = NULL;
    private(set) ?bool $htmxRenderResponseTarget = NULL;
    private bool $csrf = true;
    private(set) string $submitButtonText = "Submit";


    private const INVALID_CSRF = "Invalid CSRF Token.";
    /**
     * 
     * @param \PatrykNamyslak\Patbase $databaseConnection
     * @param string $table This is the table name for which the input fields will be fetched from, the input fields will be the columns from the table
     */
    public function __construct(protected Patbase $databaseConnection, protected string $table){
        $query = "DESCRIBE {$table};";
        try{
            $stmt = $databaseConnection->connection->query($query);
            $stmt->setFetchMode(\PDO::FETCH_OBJ);
            $this->tableStructure = $stmt->fetchAll();
            $this->fieldNames = array_column($this->tableStructure, "Field");
            return;
        }catch(Exception $e){
            echo "Form Builder Failed \n\n";
            return;
            // echo $e;
        }
    }


    /**
     * Turn an array of regular field names into placeholders that are ready for prepared statements.
     * @param array $fieldNames Database table field names that will be used in a prepared statement
     * @return string
     */
    public function createPlaceholdersFromArray(array $fieldNames): string{
        foreach($fieldNames as &$placeholder){
            $placeholder = ":" . $placeholder;
        }
        return implode(",", $fieldNames);
    }


    /**
     * Adds a div surrounding the input and its label, this is if you want to use flexbox or a grid layout for the form.
     * @return static
     */
    public function wrapFields(){
        $this->wrapField = true;
        return $this;
    }

    public function submit(array $formData){
        if (!$this->validateCsrfToken($formData["csrf_token"])){
            exit(self::INVALID_CSRF);
        }
        unset($formData["csrf_token"]);
        $placeholders = $this->createPlaceholdersFromArray($this->fieldNames);
        foreach($this->tableStructure as $column){
            $formData[$column->Field] = match($column->Type){
                "json" => json_encode(explode(",", $formData[$column->Field])),
                default => $formData[$column->Field],
            };
        }
        $columnNames = implode(",", $this->fieldNames);
        $query = "INSERT INTO `{$this->table}` ({$columnNames}) VALUES($placeholders);";
        try{
            $this->databaseConnection->prepare($query, $formData)->execute();
            echo "Form submitted!";
            return;
        }catch(Exception $e){
            echo $e;
            echo "An error has occurred";
            return;
        }
    }


    /**
     * Sets where the form should send data.
     * @param string $destination URI or URL
     */
    public function action(string $destination){
        $this->action = $destination;
        return $this;
    }

    public function submitButtonText(string $value){
        $this->submitButtonText = $value;
        return $this;
    }


    public function method(RequestMethod|string $RequestMethod){
        if (is_string($RequestMethod) and !in_array($RequestMethod, array_column(RequestMethod::cases(), "value"))){
            throw new Exception("The RequestMethod was not set as the value provided is invalid");
        }
        $this->method = match(true){
            $RequestMethod instanceof RequestMethod => $RequestMethod->value,
            default => $RequestMethod,
        };
        return $this;
    }

    /**
     * You can use this to modify the tableStructure used by the generator, for example if you want to make a login form and you only need specific fields such as username and password but not userID.
     * @param array $tableStructure
     * @return static
     */
    // public function tableStructure(array $tableStructure){
    //     $this->tableStructure = $tableStructure;
    //     return $this;
    // }
    
    public function omitFields(array $columnNames){
        foreach($columnNames as $columnName){
            unset($this->tableStructure[array_search($columnName, $this->tableStructure)]);
        }
        return $this;
    }
    /**
     * Pass an array of columns that are in the target table that the form is being generated from to remove them from the final form, this can cause errors if the database does not have default values for these columns upon form submission or you don't handle form submission correctly by modifying the submit functionality.
     * @return static
     */
    public function onlyUse(array $columns){
        // Check if the columns are in the table structure
        if (empty(array_diff($columns, $this->tableStructure))){
            $this->tableStructure = $columns;
        }else{
            throw new Exception("Invalid column names provided.");
        }
        return $this;
    }


    public function noCsrf(){
        $this->csrf = false;
        return $this;
    }
    private function createCsrfToken(){
        return bin2hex(random_bytes(32));
    }
    private function setCsrfToken(){
        $_SESSION["csrf_token"] = $this->createCsrfToken();
    }
    /**
     * Returns the currently set CSRF token and if there is none set, it sets it, then returns it.
     * @return string
     */
    private function csrfToken(){
        if (!$_SESSION["csrf_token"]){
            $this->setCSRFToken();
        }
        return $_SESSION["csrf_token"];
    }
    private function validateCsrfToken(string $token){
        return $token === $this->csrfToken();
    }

    /**
     * Makes the form use HTMX for the request.
     * @param string $responseTarget This needs to be a valid CSS selector, i.e ".response" or "nearest .response" for htmx to be able to locate your element
     * @param mixed $renderResponseElement This defaults to an element called .response and ignores the users set responseTargetElement
     * @return static
     */
    public function htmx(string $responseTargetElement = "this", bool $renderResponseElement = true,  HtmxSwapMode $swapMode = HtmxSwapMode::innerHTML): static{
        $this->htmx = true;
        $this->htmxRenderResponseTarget = $renderResponseElement;
        // Defaults to .response if the user wants the form to default to its own response element / use the rendered one
        $this->htmxResponseTarget = match($this->htmxRenderResponseTarget){
            true => ".response",
            false => $responseTargetElement,
        };
        $this->htmxSwapMode = $swapMode;
        return $this;
    }

    /**
     * Renders the form
     */
    public function render(string $formTitle, bool $renderLabels = true){
        ?>
        <h2><?= $formTitle ?></h2>
        <?php
        if ($this->htmx):
            //  Render default response element
            if ($this->htmxRenderResponseTarget): ?>
            <div class="response"></div>
            <?php
            endif;
            // Inject htmx dependency
            ?>
            <script src="https://cdn.jsdelivr.net/npm/htmx.org@2.0.8/dist/htmx.min.js"></script>
            <form hx-<?= $this->method ?>="<?= $this->action ?>" hx-swap="<?= $this->htmxSwapMode->value ?>" hx-target="<?= $this->htmxResponseTarget ?>">
        <?php
        else: ?>
            <form action="<?= $this->action ?>" method="<?= $this->method ?>">
        <?php
        endif;
        if ($this->csrf): ?>
        <input type="hidden" name="csrf_token" value="<?= $this->csrfToken() ?>">
        <?php
        endif;
        foreach ($this->tableStructure as $column):
            if ($this->wrapField): ?>
                <div>
            <?php
            endif;
            // Skip Auto incremented columns
            if (Column::isAutoIncrement(column: $column)){
                continue;
            }
            $Input = new Input;
            $Input
            ->dataTypeExpectedByDatabase($column->Type)
            ->name($column->Field)
            ->values($column->Type)
            ->type($column->Type)
            ->default($column->Default)
            ->required(Column::isNullable($column) === false);

            // Render the input field
            if($renderLabels){
                $Input
                ->label()
                ->renderLabel();
            }
            // handle a field that accepts multiple inputs
            if ($Input->getColumnTypeInString() === "json"){
                $Input->json()->textField();
            }else{
                match($Input->type){
                    InputType::TEXT_AREA => $Input->textArea(),
                    InputType::TEXT => $Input->textField(),
                    InputType::DROPDOWN => $Input->dropdown(),
                    InputType::RADIO => $Input->radio(),
                };
            }
            if ($this->wrapField): ?>
                </div>
            <?php
            endif;
        endforeach;
        ?>
        <button type="submit"><?= $this->submitButtonText ?></button>
        </form>
        <?php
    }
}