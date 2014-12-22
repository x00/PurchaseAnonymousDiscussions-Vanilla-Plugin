<?php if (!defined('APPLICATION')) exit(); ?>
<div class="AnoymousDiscussionsProfile">
<br/>
<?php
echo '<h2  class="H">'.T('Anonymous Discussions').'</h2>';
if ($this->Data['AnonymousDiscussions']) {
   echo '<div class="Info Empty">'.sprintf(T('You have %s Anonymous Discussion Posts left, feel free to start a %s, checking "Post as Anonymous". Or you could buy some %s.'),$this->Data['AnonymousDiscussions'],sprintf(T(C('EnabledPlugins.QnA')? T('%s or %s'):'%s'),Anchor('New Discussion','/post/discussion'),Anchor('New Question','/post/question')),Anchor('more',C('Plugins.MarketPlace.StoreURI','store').'/type/PurchaseAnonymousDiscussions')).'</div>';
} else {
   echo '<div class="Info Empty">'.sprintf(T('You do not have any Anonymous Discussion Posts, you can purchase them %s.'),Anchor('here',C('Plugins.MarketPlace.StoreURI','store').'/type/PurchaseAnonymousDiscussions')).'</div>';
}
?>
</div>
<?php
