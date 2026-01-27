<?php
namespace PatrykNamyslak\FormBuilder;

enum InputType:int{

    /**
     * JSON Columns can have the type of TEXT and NUMBER
     */

    /** Varchar Columns */
    case TEXT = 1;
    // Short Text, Medium Text and Long Text columns
    case TEXT_AREA = 2;
    // Integer columns
    case NUMBER = 3;
    // Enum / Set columns
    case DROPDOWN = 4;
    // Timestamp columns
    case DATE = 5;
    // Boolean columns
    case RADIO = 6;
    case PASSWORD = 7;

}