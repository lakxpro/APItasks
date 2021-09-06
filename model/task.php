<?php

class TaskException extends Exception {

}

class Task {
    private $_id;
    private $_title;
    private $_description;
    private $_deadline;
    private $_completed;

    public function __construct($id,$title,$description,$deadline,$completed){
        $this->setId($id);
        $this->setTitle($title);
        $this->setDescription($description);
        $this->setDeadline($deadline);
        $this->setCompleted($completed);
    }
    public function returnTaskAsArray(){
        $task = array();
        $task['id'] = $this->getId();
        $task['title'] = $this->getTitle();
        $task['description'] = $this->getDescription();
        $task['deadline'] = $this->getDeadline();
        $task['completed'] = $this->getCompleted();

        return $task;
    }

    public function getId() {
        return $this->_id;
    }

    public function getTitle() {
        return $this->_title;
    }

    public function getDescription() {
        return $this->_description;
    }

    public function getDeadline() {
        return $this->_deadline;
    }

    public function getCompleted() {
        return $this->_completed;
    }

    public function setId($id) {
        if (($id !== null) && (!is_numeric($id) || $id <= 0 || $id > PHP_INT_MAX || $this->_id !== null)) {
            throw new TaskException("Task ID error => id value: ".$id);
        }
        $this->_id = $id;
    }

    public function setTitle($title){
        if (strlen($title) <= 0 || strlen($title) > 255) {
            throw new TaskException("Task title error => title value: ".$title);
        }
        $this->_title = $title;
    }

    public function setDescription($description){
        if (($description !== null)  && (strlen($description)> 16777215)) {
            throw new TaskException("Task description error => description value: ".$description);
        } 
        $this->_description = $description;
    }

    public function setDeadline($deadline){
        if ($deadline !== null && date_format(date_create_from_format('d/m/Y H:i', $deadline), 'd/m/Y H:i') != $deadline){
            throw new TaskException("Task deadline date time error => datetime value: ".$deadline);
        }
        $this->_deadline = $deadline;
    }

    public function setCompleted($completed) {
        if (strtoupper($completed) !== 'Y' && strtoupper($completed) !== 'N' ){
            throw new TaskException("Task completed error => completed value: ".$completed);
        }
        $this->_completed = $completed;
    }
}




?>