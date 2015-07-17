<?php
namespace Craft;

class m150716_010201_Market_NameBasedOrderStatuses extends BaseMigration
{
    public function safeUp()
    {

        $this->alterColumn('market_orderstatuses','color',"enum('green', 'orange', 'red', 'blue', 'yellow', 'pink', 'purple', 'turquoise', 'light', 'grey', 'black') NOT NULL DEFAULT 'green'");

        $statuses = craft()->db->createCommand()
            ->select('*')
            ->from('market_orderstatuses')
            ->queryAll();

        $colors = ['green', 'orange', 'red', 'blue', 'yellow', 'pink', 'purple', 'turquoise', 'light', 'grey', 'black'];

        $lenStatuses = count($statuses);
        $lenColors = count($colors);
        if($lenStatuses){
            for ($i = 0; $i <= $lenStatuses-1; $i++){
                if ($i == $lenColors-1){
                    reset($colors);
                }
                $color = next($colors);
                $id = $statuses[$i]['id'];
                craft()->db->createCommand()->update('market_orderstatuses',['color'=>$color],'id = :sourceId',[':sourceId'=>$id]);
            }
        }


        return true;
    }
}