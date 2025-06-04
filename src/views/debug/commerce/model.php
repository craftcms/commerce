<?php use craft\commerce\helpers\DebugPanel;
use craft\helpers\ArrayHelper;

if (isset($model)): ?>
    <h2 style="font-size: 0.75rem;"><?= $model::class ?></h2>
    <div class="table-responsive">
        <table class="table table-condensed table-bordered table-striped table-hover"
               style="table-layout: fixed;">
            <tbody>
            <?php foreach ($model->toArray($fields ?? array_keys($model->fields()), $extraFields ?? $model->extraFields()) as $attr => $value): ?>
                <?php if (is_array($value) && ArrayHelper::isIndexed($value) && count(array_filter(array_values($value), 'is_array')) > 0): ?>
                    <?php foreach ($value as $key => $val): ?>
                        <?php echo DebugPanel::renderModelAttributeRow($attr, $val, sprintf('%s.%s', $attr, $key)); ?>
                    <?php endforeach; ?>
                <?php else: ?>
                    <?php echo DebugPanel::renderModelAttributeRow($attr, $value); ?>
                <?php endif; ?>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
