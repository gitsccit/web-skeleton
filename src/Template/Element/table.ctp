<?php
/**
 * Displays an interactive data table for use with the cakephp Paginator
 *
 * @var \App\View\AppView $this
 */
?>
<div id="priority-table" class="table-responsive">
    <table id="data" class="table-list">
        <?php if (!empty($priority)): ?>
            <colgroup>
                <?php foreach ($priority as $p): ?>
                    <col data-priority="<?= $p ?>"/>
                <?php endforeach; ?>
            </colgroup>
        <?php endif; ?>
        <?php if (!empty($thead)): ?>
            <thead>
            <?= $thead ?>
            </thead>
        <?php endif; ?>
        <?php if (!empty($tbody)): ?>
            <tbody>
            <?php foreach ($tbody as $row): ?>
                <?= $row ?>
            <?php endforeach; ?>
            </tbody>
        <?php endif; ?>
    </table>
    <div class="paginator">
        <p><?= $this->Paginator->counter(['format' => __('Page {{page}} of {{pages}}, showing {{current}} record(s) out of {{count}} total')]) ?></p>
        <ul class="pagination">
            <?= $this->Paginator->first('<< ' . __('first')) ?>
            <?= $this->Paginator->prev('< ' . __('previous')) ?>
            <?= $this->Paginator->numbers() ?>
            <?= $this->Paginator->next(__('next') . ' >') ?>
            <?= $this->Paginator->last(__('last') . ' >>') ?>
        </ul>
    </div>
</div>
