<?php
/**
 * @var \Skeleton\View\AppView $this
 * @var \Cake\ORM\Entity $entity
 * @var string[] $accessibleFields
 * @var string[] $associations
 * @var string[] $fields
 * @var string $className
 * @var string $displayField
 */
?>
<nav class="large-3 medium-4 columns" id="actions-sidebar">
    <ul class="side-nav">
        <li class="heading"><?= __('Actions') ?></li>
        <li><?= $this->Html->link(__("List $className"), ['action' => 'index']) ?></li>
        <?php foreach ($associations as $association): ?>
            <?php $associationSpaceDelimited = implode(' ', preg_split('/(?=[A-Z])/', $association)); ?>
            <li><?= $this->Html->link(__("List $associationSpaceDelimited"),
                    ['controller' => $association, 'action' => 'index']) ?></li>
            <li><?= $this->Html->link(__("New $associationSpaceDelimited"),
                    ['controller' => $association, 'action' => 'add']) ?></li>
        <?php endforeach; ?>
    </ul>
</nav>
<div class="<?= $className ?> form large-9 medium-8 columns content">
    <?= $this->Form->create($entity) ?>
    <fieldset>
        <legend><?= __('Add ' . \Cake\Utility\Inflector::classify($className)) ?></legend>
        <?php
        foreach ($accessibleFields as $field) {
            if (isset($$field)) {
                echo $this->Form->control("$field._ids", ['options' => $$field]);
                continue;
            }
            echo $this->Form->control($field);
        }
        ?>
    </fieldset>
    <?= $this->Form->button(__('Submit')) ?>
    <?= $this->Form->end() ?>
</div>
