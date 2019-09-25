<?php
/**
 * @var \Skeleton\View\AppView $this
 * @var \Cake\ORM\Entity[]|\Cake\Collection\CollectionInterface $entities
 * @var string[] $associations
 * @var string[] $fields
 * @var string $className
 * @var string $displayField
 */
?>
<nav class="large-3 medium-4 columns" id="actions-sidebar">
    <ul class="side-nav">
        <li class="heading"><?= __('Actions') ?></li>
        <li><?= $this->Html->link(__('New ' . \Cake\Utility\Inflector::classify($className)),
                ['action' => 'add']) ?></li>
        <?php foreach ($associations as $association): ?>
            <?php $associationSpaceDelimited = implode(' ', preg_split('/(?=[A-Z])/', $association)); ?>
            <li><?= $this->Html->link(__("New " . \Cake\Utility\Inflector::singularize($associationSpaceDelimited)),
                    ['controller' => $association, 'action' => 'add']) ?></li>
            <li><?= $this->Html->link(__("List $associationSpaceDelimited"),
                    ['controller' => $association, 'action' => 'index']) ?></li>
        <?php endforeach; ?>
    </ul>
</nav>
<div class="<?= lcfirst($className) ?> index large-9 medium-8 columns content">
    <h3><?= __($className) ?></h3>
    <table cellpadding="0" cellspacing="0">
        <thead>
        <tr>
            <?php foreach ($fields as $field): ?>
                <th scope="col"><?= $this->Paginator->sort($field) ?></th>
            <?php endforeach; ?>
            <th scope="col" class="actions"><?= __('Actions') ?></th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($entities as $entity): ?>
            <tr>
                <?php foreach ($fields as $field) {
                    echo $this->Utils->display($entity->get($field));
                } ?>
                <td class="actions">
                    <?= $this->Html->link(__('View'), ['action' => 'view', $entity->id]) ?>
                    <?= $this->Html->link(__('Edit'), ['action' => 'edit', $entity->id]) ?>
                    <?= $this->Form->postLink(__('Delete'), ['action' => 'delete', $entity->id],
                        ['confirm' => __('Are you sure you want to delete # {0}?', $entity->id)]) ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <div class="paginator">
        <ul class="pagination">
            <?= $this->Paginator->first('<< ' . __('first')) ?>
            <?= $this->Paginator->prev('< ' . __('previous')) ?>
            <?= $this->Paginator->numbers() ?>
            <?= $this->Paginator->next(__('next') . ' >') ?>
            <?= $this->Paginator->last(__('last') . ' >>') ?>
        </ul>
        <p><?= $this->Paginator->counter(['format' => __('Page {{page}} of {{pages}}, showing {{current}} record(s) out of {{count}} total')]) ?></p>
    </div>
</div>
