<?php if (isset($model, $attributes)): ?>
    <h2 style="font-size: 0.75rem;"><?= get_class($model) ?></h2>
    <div class="table-responsive">
        <table class="table table-condensed table-bordered table-striped table-hover"
               style="table-layout: fixed;">
            <tbody>
            <?php foreach ($attributes as $attr => $value): ?>
                <tr>
                    <th><?= $attr ?></th>
                    <?php if (is_string($value)): ?>
                        <?php if (strpos($attr, 'html') !== -1): ?>
                            <td><code><?php echo craft\helpers\Html::encode($value); ?></code></td>
                        <?php else: ?>
                            <td><code><?php echo $value; ?></code></td>
                        <?php endif; ?>
                    <?php else: ?>
                        <td><code><?php echo \yii\helpers\VarDumper::dumpAsString($value); ?></code></td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
