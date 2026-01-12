<?php
namespace PatrykNamyslak\FormBuilder;

use Exception;
use PatrykNamyslak\Patbase;


class Form{
    /** This will be an array of objects for all of the columns*/
    private array $tableStructure;
    private string $action;
    private string $method;

    /**
     * 
     * @param \PatrykNamyslak\Patbase $databaseConnection
     * @param string $table This is the table name for which the input fields will be fetched from, the input fields will be the columns from the table
     */
    public function __construct(protected Patbase $databaseConnection, string $table){
        $query = "DESCRIBE {$table};";
        try{
            $stmt = $databaseConnection->connection->query($query);
            $stmt->setFetchMode(\PDO::FETCH_OBJ);
            $this->tableStructure = $stmt->fetchAll();
            return;
        }catch(Exception $e){
            echo "Form Builder Failed \n\n";
            return;
            // echo $e;
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
     * Renders the form
     */
    public function render(bool $renderLabels = true){
        ?>
        <form action="<?= $this->action ?>" method="<?= $this->method ?>">
        <?php
        foreach ($this->tableStructure as $column){
            $Input = new Input;
            $Input
            ->type($column->Type)
            ->dataTypeExpectedByDatabase($column->Type)
            ->name($column->Field)
            ->values($column->Type)
            ->default($column->Default)
            ->required(Table::isColumnNullable($column) === false);

            // Render the input field
            if($renderLabels){
                $Input
                ->label()
                ->renderLabel();
            }
            // handle a field that accepts multiple inputs
            if ($Input->getTypeInString() === "json"){
                $Input->acceptMultipleValues()->textField();
            }else{
                match($Input->type){
                    InputType::TEXT_AREA => $Input->textArea(),
                    InputType::TEXT => $Input->textField(),
                    InputType::DROPDOWN => $Input->dropdown(),
                };
            }
        }
        ?>
        </form>
        <?php
    }
}