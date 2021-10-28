<?php if (isset($model, $attributes, $toArrayAttributes)): ?>
    <h2 style="font-size: 0.75rem;"><?= get_class($model) ?></h2>
    <div class="table-responsive">
        <table class="table table-condensed table-bordered table-striped table-hover"
               style="table-layout: fixed;">
            <tbody>
            <?php foreach ($attributes as $attr): ?>
                <?php $attrValue = $model->{$attr}; ?>
                <?php if (is_array($attrValue)): ?>
                    <?php foreach($attrValue as $key => $value): ?>
                        <?php $value = in_array($attr, $toArrayAttributes, true) ? $value->toArray() : $value; ?>
                        <?php echo \craft\commerce\helpers\DebugPanel::renderModelAttributeRow($attr, $value, sprintf('%s.%s', $attr, $key)); ?>
                    <?php endforeach; ?>
                <?php else: ?>
                    <?php $attrValue = in_array($attr, $toArrayAttributes, true) ? $attrValue->toArray() : $attrValue; ?>
                    <?php echo \craft\commerce\helpers\DebugPanel::renderModelAttributeRow($attr, $attrValue); ?>
                <?php endif; ?>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
