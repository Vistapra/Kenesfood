<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

require_once(APPPATH. 'libraries/TreeNode.php');

class Tree {
    public $root;

    public function init($value) {
        $this->root = new TreeNode($value);
    }

    public function add($value, $parentValue) {
        $parent = $this->find($this->root, $parentValue);
        if ($parent) {
            $parent->addChild(new TreeNode($value));
        }
    }

    private function find(TreeNode $node, $value) {
        if ($node->value === $value) {
            return $node;
        }

        foreach ($node->children as $child) {
            $result = $this->find($child, $value);
            if ($result) {
                return $result;
            }
        }

        return null;
    }

    public function printTree($node = null, $prefix = '') {
        if ($node === null) {
            $node = $this->root;
        }

        echo $prefix . $node->value . "\n";
        foreach ($node->children as $child) {
            $this->printTree($child, $prefix . '--');
        }
    }
}
