<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

class TreeNode {
    public $value;
    public $children;

    public function __construct($value) {
        $this->value = $value;
        $this->children = [];
    }

    public function addChild(TreeNode $child) {
        $this->children[] = $child;
    }
}
