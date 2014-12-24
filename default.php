<?php if (!defined('APPLICATION')) exit();
// Define the plugin:
$PluginInfo['PurchaseAnonymousDiscussions'] = array(
   'Name' => 'Purchase Anonymous Discussions',
   'Description' => "Allows members to purchase Anonymous Discussions",
   'Version' => '0.1.5b',
   'RequiredPlugins' => array('MarketPlace' => '0.1.9b'),
   'RequiredApplications' => array('Vanilla' => '2.1'),
   'Author' => 'Paul Thomas',
   'AuthorEmail' => 'dt01pqt_pt@yahoo.com',
   'AuthorUrl' => 'http://www.vanillaforums.org/profile/x00'
);

class PurchaseAnonymousDiscussions extends Gdn_Plugin {
    
    protected $HasAnon = FALSE;
    protected $CanComment = FALSE;
    protected $AnonUser;
    
    public static function PreConditions($UserID,$Product){
            return array('status'=>'pass');
    }
    
    public static function AddAnonymousDiscussions($UserID,$Product,$TransactionID){
        $Quantity=1;
        $VariableMeta=MarketTransaction::GetTransactionMeta($TransactionID);
        $Meta=Gdn_Format::Unserialize($Product->Meta);
        $DefaultQuantity = GetValue('Quantity',$Meta,1);
        $DefaultQuantity = ctype_digit($DefaultQuantity)?$DefaultQuantity:1;
        $Quantity=GetValue('Quantity',$VariableMeta,$DefaultQuantity);
        $AnonymousDiscussions = UserModel::GetMeta($UserID,'AnonymousDiscussions.%','AnonymousDiscussions.');
        UserModel::SetMeta($UserID,array('Quantity'=>GetValue('Quantity',$AnonymousDiscussions,0)+$Quantity),'AnonymousDiscussions.');
        return array('status'=>'success');
        
    }
    
    public static function RemoveAnonymousDiscussions($UserID,$Quantity=1){
        $AnonymousDiscussions = UserModel::GetMeta($UserID,'AnonymousDiscussions.%','AnonymousDiscussions.');
        UserModel::SetMeta($UserID,array('Quantity'=>GetValue('Quantity',$AnonymousDiscussions,0)-$Quantity),'AnonymousDiscussions.');
    }
    
    public function MarketPlace_LoadMarketPlace_Handler($Sender){
        $Options = array(
            'Meta'=>array('Quantity'),
            'RequiredMeta'=>array('Quantity'),
            'ValidateMeta'=>array('Quantity'=>'Integer'),
            'VariableMeta'=>array('Quantity'),
            'ReturnComplete'=>'/profile/anonymousdiscussions'
        );
        $Sender->RegisterProductType('PurchaseAnonymousDiscussions','Allows members to purchase Anonymous Discussions',$Options,'PurchaseAnonymousDiscussions::PreConditions','PurchaseAnonymousDiscussions::AddAnonymousDiscussions');
    }
    
    public function ProfileController_AnonymousDiscussions_Create($Sender){
        $AnonymousDiscussions = UserModel::GetMeta(Gdn::Session()->UserID,'AnonymousDiscussions.%','AnonymousDiscussions.');
        $Quantity = GetValue('Quantity',$AnonymousDiscussions,0);
        $Sender->SetData('AnonymousDiscussions',$Quantity);
        $Sender->GetUserInfo(Gdn::Session()->UserID, Gdn::Session()->User->Name);
        $ThemeViewLoc = CombinePaths(array(
            PATH_THEMES, $Sender->Theme,'views', 'purchaseanonymousdiscussions'
        ));
        $View='';
        if(file_exists($ThemeViewLoc.DS.'anonymousdiscussions.php')){
            $View=$ThemeViewLoc.DS.'anonymousdiscussions.php';
        }else{
            $View=dirname(__FILE__).DS.'views'.DS.'anonymousdiscussions.php';
        }
        $Sender->SetTabView('AnonymousDiscussions', $View, 'Profile', 'Dashboard');
        $Sender->Render();
    }
    
    public function ProfileController_AddProfileTabs_Handler($Sender){
        $Sender->AddProfileTab('AnonymousDiscussions','profile/anonymousdiscussions',
                        'AnonymousDiscussions',T('Anonymous Discussions'));
    }
    
    public function Base_BeforeControllerMethod_Handler($Sender,$Args){
        if($Args['Controller']->PageName()!='post')
            return;
        $AnonymousDiscussions = UserModel::GetMeta(Gdn::Session()->UserID,'AnonymousDiscussions.%','AnonymousDiscussions.');
        $this->HasAnon = GetValue('Quantity',$AnonymousDiscussions,0);
        if(strtolower($Args['Controller']->RequestMethod)=='editdiscussion'){
            $DiscussionID = GetValue(0,$Args['Controller']->RequestArgs);
            if($this->AnnonDiscussionUser($DiscussionID)){
                $DiscussionModel = new DiscussionModel();
                $Discussion = $DiscussionModel->GetID($DiscussionID);
                Gdn::Session()->SetPermission('Vanilla.Discussions.Edit', array('-1',$Discussion->CategoryID));
            }
        } else if(strtolower($Args['Controller']->RequestMethod)=='editcomment'){
            $CommentID = GetValue(0,$Args['Controller']->RequestArgs);
            $CommentModel = new CommentModel();
            $Comment = $CommentModel->GetID($CommentID);
            if($this->AnnonDiscussionUser($Comment->DiscussionID)){
                $DiscussionModel = new DiscussionModel();
                $Discussion = $DiscussionModel->GetID($Comment->DiscussionID);
                Gdn::Session()->SetPermission('Vanilla.Comments.Edit', array('-1',$Discussion->CategoryID));
            }
        }
    }

    public function Base_DiscussionFormOptions_Handler($Sender,$Args) {
         if(!Gdn::Session()->CheckPermission('Plugins.MarketPlace.UseStore'))
            return
         
        $Discussion = GetValue('Discussion',$Sender)?$Sender->Discussion:(GetValue('Discussion',$Args)?$Args['Discussion']:false);
        $BuyMore = Wrap(T('BuyMoreSpacer',' &nbsp; ').Anchor(T('Buy More'),C('Plugins.MarketPlace.StoreURI','store').'/type/PurchaseAnonymousDiscussions'),'span');
        $BuySome = Wrap(T('BuyMoreSpacer',' &nbsp; ').Anchor(T('Buy Some'),C('Plugins.MarketPlace.StoreURI','store').'/type/PurchaseAnonymousDiscussions'),'span');
        $Message = T('Anonymous Post').$BuyMore;
        if(GetValue('DiscussionID',$Discussion))
            $BuySome='';
        if(!$Discussion && $this->HasAnon){
            $Args['Options'].='<li>'.$this->ShowOption($Sender->Form,$Message).'</li>';
        }else if($Discussion && GetValue('AnonUser',$Discussion)){
            $Args['Options'].='<li>'.$this->ShowOption($Sender->Form,$Message,array('checked'=>'checked','disabled'=>'disabled'),TRUE).'</li>';
        }else{
            $Message = T('Post as Anonymous').$BuySome;
            $Args['Options'].='<li>'.$this->ShowOption($Sender->Form,$Message,array('disabled'=>'disabled')).'</li>';
        }
    }
    
    public function PostController_Render_Before($Sender){
        if(strtolower($Sender->RequestMethod)=='question'){
            $Sender->View=$this->GetView('post.php');
        }
    }


    public function ShowOption($Form,$Message,$Params=array(),$Hidden=FALSE){
        $Options = '';
        $Options .= $Form->CheckBox('AnonUser',$Message,$Params);
        if($Hidden){
            $Form->AddHidden('AnonUser',1);
            $Options .= $Form->Hidden('AnonUser',array('value'=>1));
        }
        return $Options;
    }
    
    public function DiscussionModel_BeforeSaveDiscussion_Handler($Sender,&$Args){
        $Feilds = &$Args['FormPostValues'];
        if(GetValue('DiscussionID',$Feilds) ||!$this->HasAnon || !GetValue('AnonUser',$Feilds)){
            if(isset($Feilds['AnonUser']) && $this->AnnonDiscussionUser(GetValue('DiscussionID',$Feilds))){
                $Feilds['AnonUser']=1;
            }else{
                $Feilds['AnonUser']=0;
            }
            return;
        }
        $AnonUser = Gdn::UserModel()->GetByUsername(C('Plugins.PurchaseAnonymousDiscussions.UserName','AnonymousUser'));
        $Feilds['InsertUserID'] = $AnonUser->UserID;
        $Feilds['UpdateUserID'] = $AnonUser->UserID;
        $Feilds['AnonUserHash'] = md5('AnonymousUser'.Gdn::Session()->UserID.Gdn::Session()->User->DateFirstVisit);
    }
    
    public function DiscussionModel_AfterSaveDiscussion_Handler($Sender,$Args){
        $Feilds = $Args['FormPostValues'];
        if(!$this->HasAnon || !$Feilds['AnonUser'])
            return;
        UserModel::SetMeta(Gdn::Session()->UserID,array('DiscussionID.'.$Feilds['DiscussionID']=>1,'Quantity'=>$this->HasAnon-1),'AnonymousDiscussions.');
    }
    
    public function CommentModel_BeforeSaveComment_Handler($Sender,&$Args){
        $Feilds = &$Args['FormPostValues'];
        if($Fields['CommentID']) 
            return;
        $DiscussionModel = new DiscussionModel();
        $Discussion = $DiscussionModel->GetID($Feilds['DiscussionID']);

        if(!$this->AnonCommentValid($Discussion))
            return;
        $AnonUser = Gdn::UserModel()->GetByUsername(C('Plugins.PurchaseAnonymousDiscussions.UserName','AnonymousUser'));
        $Feilds['InsertUserID'] = $AnonUser->UserID;
        $Feilds['UpdateUserID'] = $AnonUser->UserID;
    }
    
    private function AnnonDiscussionUser($DiscussionID){
        $Meta = UserModel::GetMeta(Gdn::Session()->UserID,'AnonymousDiscussions.DiscussionID.'.$DiscussionID,'AnonymousDiscussions.');
        return $Meta['DiscussionID.'.$DiscussionID];
    }
    
    private function AnonCommentValid($Discussion){
        if(!GetValue('AnonUser',$Discussion))
            return FALSE;
        $AnonUserHash = Getvalue('AnonUserHash',$Discussion);

        if(Gdn::Session()->IsValid() && $AnonUserHash!=md5('AnonymousUser'.Gdn::Session()->UserID.Gdn::Session()->User->DateFirstVisit)){
            return FALSE;
        }else{
            $DiscussionID = GetValue('DiscussionID',$Discussion);
            return $this->AnnonDiscussionUser($DiscussionID);
        }
    }
    
    public function DiscussionController_Render_Before($Sender){
        if(!$this->AnonCommentValid($Sender->Discussion))
            return;
        $this->CanComment = TRUE;
        $this->AnonUser = Gdn::UserModel()->GetByUsername(C('Plugins.PurchaseAnonymousDiscussions.UserName','AnonymousUser'));
    }
    
    public function DiscussionsController_Render_Before($Sender){
        $this->AnonUser = Gdn::UserModel()->GetByUsername(C('Plugins.PurchaseAnonymousDiscussions.UserName','AnonymousUser'));
    }
    
    public function PostOptions($Sender,&$Args,$ObjectName){
        $Object = $Args[$ObjectName];
        return;
        if(!$this->CanComment || !$this->AnonUser->UserID  || $Object->InsertUserID!=$this->AnonUser->UserID)
            return;
        if($Args['Type'] == 'Discussion'){
            if(!GetValue('EditDiscussion',$Args['DiscussionOptions']))
            $Args['DiscussionOptions']['EditDiscussion'] = array('Label'=>T('Edit'), 'Url'=>'/vanilla/post/editdiscussion/'.$Object->DiscussionID, 'Class'=>'EditDiscussion');
        }else if($Args['Type'] == 'Comment'){
            if(!GetValue('EditComment',$Args['CommentOptions']))
                $Args['CommentOptions']['EditComment'] = array('Label'=>T('Edit'), 'Url'=>'/vanilla/post/editcomment/'.$Object->CommentID, 'Class'=>'EditComment');
        }
    }
    
    public function DiscussionController_CommentOptions_Handler($Sender,&$Args) {
        $this->PostOptions($Sender,$Args,'Comment');
    }
    
    public function DiscussionController_DiscussionOptions_Handler($Sender,&$Args) {
        $this->PostOptions($Sender,$Args,'Discussion');
    }
    
    public function DiscussionsController_BeforeDiscussionContent_Handler($Sender,$Args){
        if(!$this->AnonCommentValid($Args['Discussion']))
            return;
        $Sender->ShowOptions=TRUE;
        $this->CanComment = TRUE;
    }
    
    public function DiscussionsController_DiscussionOptions_Handler($Sender,&$Args){
        if(!$this->CanComment)
            return;
        $this->PostOptions($Sender,$Args,'Discussion');
    }
    
    public function Base_BeforeDispatch_Handler($Sender){
        
        if(C('Plugins.PurchaseAnonymousDiscussions.Version')!=$this->PluginInfo['Version'])
            $this->Structure();
    }
    
    public function Setup() {

        $this->Structure();
    }
    
    public function Structure(){
        Gdn::Structure()
            ->Table('Discussion')
            ->Column('AnonUser','int(4)',0)
            ->Column('AnonUserHash','char(32)',null)
            ->Set();
        $AnonUser = Gdn::UserModel()->GetByUsername(C('Plugins.PurchaseAnonymousDiscussions.UserName','AnonymousUser'));
        if(!$AnonUser){
            Gdn::UserModel()->Save(
                array(
                    'Name' => C('Plugins.PurchaseAnonymousDiscussions.UserName','AnonymousUser'),
                    'Password' => uniqid(),
                    'Email' => str_replace('@','+anon@',C('Garden.Email.SupportAddress') ? C('Garden.Email.SupportAddress') : 'noreply@gmail.com'),
                    'DateOfBirth' => '1975-09-16 00:00:00',
                    'Verified' => 1,
                    'Admin' => 1
                )
            );
            RemoveFromConfig('Plugins.PurchaseAnonymousDiscussions.UserName');
            
        }
        
        if(!C('Plugins.PurchaseAnonymousDiscussions.UserName')){
            SaveToConfig('Plugins.PurchaseAnonymousDiscussions.UserName','AnonymousUser');
        }
        
        SaveToConfig('Plugins.PurchaseAnonymousDiscussions.Version', $this->PluginInfo['Version']);
        
    }
    
    
    

}
