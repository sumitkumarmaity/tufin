<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Billing extends MY_Controller {
    public function __construct(){
        parent::__construct();        
        $this->load->model('billing_model');
    }
    public function index(){
        redirect(INDEXFILE."/billing/nonproject_inhouse");
    }
    
   /**
    * Created by Sumit
    * It is using for NON PROJECT INHOUSE BILL TRANSACTION
    */
    public function nonproject_inhouse(){
        //-------------------TEST-------------------------//
       // $abc =  $this->billing_model->_get_parent_id(183);
       // prd($abc);
       // $debitAccLedgerList = $this->billing_model->fetch_ledger_order_wise(array(INCOMELEDGER));
        //prd($debitAccLedgerList);
        
//        $ledgers = $this->billing_model->getTableDetails(MST_GROUP_LEDGER);
//        
//        foreach($ledgers as $values){
//            $top_parent = 0;
//            if($values->LedgerId > 5){
//               $top_parent =  $this->billing_model->_get_parent_id($values->LedgerId); //prd($top_parent);
//            }
//            $this->billing_model->update_top_parent($values->LedgerId, $top_parent);
//        }
//        
//        prd('ok');
        //-------------------END TEST---------------------------------------//
        
        $userId = $_SESSION["tu_finance_user"]->UserId;
        //Set Page Title
        $this->setViewData('title', "INHOUSE BILL");
        //Get Dept. List
        $this->setViewData("departmentList", $this->makingArray($this->billing_model->getTableDetails(MST_DEPARTMENT, $deptCondition = array("OrgBranchId" => ORGBRANCHID)), "DeptId", "DeptName"));
        //Get Type List
        $this->setViewData("voucherList", $this->billing_model->getTableDetails(MST_VOUCHER_TYPE, array("VoucherTypeId" => array(ADVACE, ADJUSTMENT, FINAL_VOUCHER))));
        //Get Budget Head List
        $this->setViewData("budgetHeadList", $this->billing_model->getTableDetails(MST_BUDGET_HEAD, array("BudgetCategoryId"=>array(1,2))));
        /*
        * Function => fetch_ledger_order_wise() 
        * @param 1st=> array(ASSETLEDGER,EXPENSELEDGER), 2nd=> array('IsAdvanceInhouse'=>1,'Status'=>1)
        * First Param List Ledger Order Wise As per array value Order  Add the Second Param is Identifying Condition of Ledger as like Advance Ledger , Project Ledger etc.
        */
        //$debitAccLedgerList = $this->billing_model->fetch_ledger_order_wise(array(ASSETLEDGER,EXPENSELEDGER),array('IsAdvanceInhouse'=>1));
        //$this->setViewData("debitAccLedgerList", $debitAccLedgerList);
        //Get Outstanding Ledger List
        $this->setViewData("sundryCreditorsLedgerList", $this->billing_model->getTableDetails(MST_GROUP_LEDGER, array("LedgerId" => 123)));
        //Get Credit Account 
        $this->setViewData("creditAccLedgerList", $this->billing_model->fetchAllBankLedger());
        //Get Advance Ledger Account 
        $this->setViewData("advAccLedgerList", $this->billing_model->fetch_ledger_order_wise(array(ASSETLEDGER,EXPENSELEDGER),array('IsAdvanceInhouse'=>1),array('IsAdvanceInhouse'=>1)));
        //Get Payee list
        $this->setViewData("payeeList", $this->billing_model->get_payee_list(array(CAT_STUDENT,CAT_EMPLOYEE,CAT_DONOR,CAT_OTHER)));
        //Get Deduction Account(Cr.) List
        $this->setViewData("deductionAccountLedgerList", $this->billing_model->fetch_ledger_order_wise(array(INCOMELEDGER,LIABILITYLEDGER,ASSETLEDGER,EXPENSELEDGER),array('IsDeduction'=>1),array('IsDeduction'=>1)));
        //Get Cheque Number List
        $this->setViewData("chequeList", $this->billing_model->getTableDetails(MST_BANK_CHEQUE_DETAILS,$chequeCondition = array("InsModeStatusId" => array(NOT_ISSUED_MODE))));
        //Get Bank Account List
        $this->setViewData("bankAccountList", $this->billing_model->getTableDetails(MST_BANK_ACCOUNT));
        //Get payment Process Mode =>cheque/DD/NEFT etc
        $this->setViewData("instrumentList", $this->billing_model->getTableDetails(MST_INSTRUMENT));
        //Get Bank Charges List
        $this->setViewData("bankChargesLedgerList",$this->billing_model->fetch_ledger_order_wise(array(EXPENSELEDGER),array('IsBankCharge' => 1),array('IsBankCharge' => 1)));
        
        //Set Advance Bill Details Section
        if($this->input->post("search_action") === 'advanceBill'){
           $BillNo = $this->input->post("BillNo"); 
           $BillCondition = array('ConsolidateBillNo' => $BillNo, 'VoucherTypeId'=> 1, 'DrCrId' => 1,'IsPartialPost'=>0,'ScreenSectionId'=>1);
           $advanceBillDetails = $this->billing_model->fetchAllData(VIEW_TRANSACTION_POSTING_ACCOUNTS_DETAILS,$BillCondition);
           
            if(empty($advanceBillDetails)){
                unset($_SESSION['ADVANCE_BILL_DETAILS_NPI']);
                $this->__error_handler("NO Advance Bill Found with this Bill No. (".$BillNo.")");
            }else{
                $_SESSION['ADVANCE_BILL_DETAILS_NPI'] = $advanceBillDetails; die('success');
            }
        }
        
        $ParentTransPostingId = @$_SESSION['ADVANCE_BILL_DETAILS_NPI'][0]->TransPostingId ? @$_SESSION['ADVANCE_BILL_DETAILS_NPI'][0]->TransPostingId : NULL;
        //Treansaction Posting
        $TransPostingData        = (array) @$_SESSION['TRANS_POSTING_NPI']; 
        $DebitTransList          = (array) @$_SESSION['ADD_TO_DR_LIST_NPI'];
        $DeductionList           = (array) @$_SESSION['ADD_TO_DEDUCTION_LIST_NPI'];
        $CreditTransList         = (array) @$_SESSION['ADD_TO_CR_LIST_NPI'];
        $AdvanceTransList        = (array) @$_SESSION['ADD_TO_ADVANCE_LIST_NPI'];
        $BankChargesData         = (array) @$_SESSION['ADD_BANK_CHARGES_NPI'];
        $TrPosting               = array();
        $TrPostingSubDrList      = array();
        $TrPostingSubCrList      = array();
        $TrPosSubDeductDrList    = array();
        
        $TransPostingId          = ci_decode($this->uri->segment(3) ? $this->uri->segment(3) : NULL);
        $billDate                = dateFormat($TransPostingData['BillDate'],'Y-m-d');
        $finYearId               = getFinId($billDate ? $billDate : currentDateTime('Y-m-d'));
         
        
        if($TransPostingId){
            $this->setViewData("TransPostingId", $TransPostingId);
            $this->setViewData("showPostingData", (array) $this->billing_model->getTableDetails(TRANSACTION_POSTING, array('TransPostingId' => $TransPostingId, 'IsDeleted' => 0)));
            $this->setViewData("showDebitListData", (array) $this->billing_model->getTableDetails(VIEW_TRANSACTION_POSTING_ACCOUNTS_DETAILS, array('TransPostingId' => $TransPostingId, 'IsDeleted' => 0 , 'ScreenSectionId'=>DEBIT_SECTION)));
            $this->setViewData("showDeductionListData",(array) $this->billing_model->getTableDetails(VIEW_TRANSACTION_POSTING_ACCOUNTS_DETAILS, array('TransPostingId' => $TransPostingId, 'IsDeleted' => 0,'ScreenSectionId'=>DEDUCTION_SECTION)));
            $this->setViewData("showDataAdvanceList",(array) $this->billing_model->getTableDetails(VIEW_TRANSACTION_POSTING_ACCOUNTS_DETAILS, array('TransPostingId' => $TransPostingId, 'IsDeleted' => 0,'ScreenSectionId'=>ADVANCE_SECTION)));
            $this->setViewData("showDataCreditList", (array) $this->billing_model->getTableDetails(VIEW_TRANSACTION_POSTING_ACCOUNTS_DETAILS, array('TransPostingId' => $TransPostingId, 'IsDeleted' => 0,'ScreenSectionId'=>CREDIT_SECTION)));
            $this->setViewData("showBankChargesData",(array) $this->billing_model->getTableDetails(VIEW_TRANSACTION_POSTING_ACCOUNTS_DETAILS, array('TransPostingId' => $TransPostingId, 'IsDeleted' => 0,'ScreenSectionId'=>BANK_CHARGES_SECTION)));
        }
        
        if($this->input->post("_action") == 'saveNPI'){
            //This section is block to save data when transaction date is out of Financial Year
            if(fin_lock($finYearId)){
                $this->__error_handler('This Financial Year Already Closed!!!');  
            }
            
            $TransPostingId = $this->input->post("TransPostingId");
            //Particular Data Set Array to Submit On  TRANSACTION_POSTING Table (Define for This Particular Window)
            $TrPostingData = array();
            if( $TransPostingId == NULL ){
                
                $billNoCreation                   = $this->billing_model->getLastBillNo($TransPostingData['VoucherTypeId']); //This function is Generated Auto Bill No.
               
                $TrPosting['FinId']               = $finYearId; 
                $TrPosting['OrgBranchId']         = default_organization_branch();
                $TrPosting['BillingTypeId']       = 1;//This is identyfy to Posting Screen//TU_BILLING_TYPE_MASTER->cofiguration Table
                $TrPosting['NoteDate']            = dateFormat($TransPostingData['NoteDate'],'Y-m-d');
                $TrPosting['BillNo']              = $billNoCreation['intBillNo'];
                $TrPosting['ConsolidateBillNo']   = $billNoCreation['consolidatedBillNO'];
                $TrPosting['OrderNo']             = $TransPostingData['OrderNo'];
                $TrPosting['InvoiceNo']           = $TransPostingData['InvoiceNo'];
                $TrPosting['BillDate']            = $billDate;
                $TrPosting['TransDate']           = $billDate;
                $TrPosting['OrderDate']           = dateFormat($TransPostingData['OrderDate'],'Y-m-d');
                $TrPosting['TokenNo']             = $TransPostingData['TokenNo'];
                $TrPosting['InvoiceDate']         = dateFormat($TransPostingData['InvoiceDate'], 'Y-m-d');
                $TrPosting['ApproveDate']         = dateFormat($TransPostingData['ApproveDate'], 'Y-m-d');
                $TrPosting['FileRefNo']           = $TransPostingData['FileRefNo'];
                $TrPosting['VoucherTypeId']       = $TransPostingData['VoucherTypeId'];
                $TrPosting['IsPartialPost']       = 1;
                $TrPosting['IsBilled']            = $TransPostingData['IsBilled'];
                $TrPosting['IsVoucherPosted']     = 0;
                $TrPosting['Narration']           = strtoupper($TransPostingData['Narration']);
                $TrPosting['BudgetHeadId']        = $TransPostingData['BudgetHeadId'];
                $TrPosting['BankAcRefBySection']  = $TransPostingData['BankAcRefBySection'];
                $TrPosting["IsDeleted"]           = DEFAULTDELETE;
                $TrPosting["Status"]              = DEFAULTSTATUS;
                $TrPostingData                    = array_merge($TrPosting, $this->defaultData($TransPostingId));//Set Default Coulumn(6C) Value of Table
                
                    //SERVER SIDE VALIDATION
                    $valid_field_array = array(
                        array('field'=>'NoteDate','msg'=>'Note Date Is Required'),
                        array('field'=>'VoucherTypeId','msg'=>'Voucher Type Is Required'),
                        array('field'=>'BudgetHeadId','msg'=>'Budget Category Is Required')
                    );

                    $this->valid_post_data($TrPostingData,$valid_field_array);
                    
                  
                    //Insert Rows for Adjustment for Advance Amount Transactions
                    $totAdvAmount = 0;
                    if( $TrPosting['VoucherTypeId'] == ADJUSTMENT ){
                        if(!empty($AdvanceTransList)){ 
                            $TrPostingSubAdvList = array();
                            $TrPostingAdvList    = array();
                            foreach( $AdvanceTransList as $advKey => $advValues ){
                                $totAdvAmount                            += $advValues->AdvanceAmount;
                                $TrPostingAdvList['LedgerId']             = $advValues->LedgerId; 
                                $TrPostingAdvList['PayeeId']              = $advValues->PayeeId;
                                $TrPostingAdvList['PayeeCatId']           = $advValues->PayeeCatId;
                                $TrPostingAdvList['ParentTransPostingId'] = $ParentTransPostingId;
                                $TrPostingAdvList['DeptId']               = $TransPostingData['DeptId'];
                                $TrPostingAdvList['FinId']                = $finYearId;
                                $TrPostingAdvList['TransactionDate']      = $billDate;
                                $TrPostingAdvList['TransactionNote']      = $advValues->TransactionNote;
                                $TrPostingAdvList['TransactionGroup']     = 1;
                                $TrPostingAdvList['ScreenSectionId']      = ADVANCE_SECTION;
                                $TrPostingAdvList['DebitAmount']          = 0;
                                $TrPostingAdvList['CreditAmount']         = $advValues->AdvanceAmount;
                                $TrPostingAdvList['DrCrId']               = CREDITID;
                                $TrPostingAdvList["IsDeleted"]            = DEFAULTDELETE;
                                $TrPostingAdvList["Status"]               = DEFAULTSTATUS;
                                $TrPostingAdvList["CreatedDate"]          = currentDateTime();
                                $TrPostingAdvList["CreatedBy"]            = $userId;
                                $TrPostingSubAdvList[]                    = $TrPostingAdvList; 
                                //Ledger Amount Update
                                $res = $this->billing_model->update_ledger_balance($advValues->LedgerId,$advValues->AdvanceAmount,CREDITID);
                                if(!$res){
                                  $this->__error_handler();
                                }
                            }
                        }else{
                           $this->__error_handler('Enter Advance Bill');
                        }
                    }
                    
                    //Insert Rows for Bank Charges Amount(Debit) Transactions 
                    $totBankChargesAmt = 0;
                    if(!empty($BankChargesData)){
                        foreach($BankChargesData as $key => $value){
                            $TrPosBCDrList                    = array();
                            $TrPosSubBCDrList                 = array();
                            $totBankChargesAmt               += $value->BCAmount;
                            
                            $TrPosBCDrList['LedgerId']        = $value->LedgerId;
                            $TrPosBCDrList['DebitAmount']     = $value->BCAmount;
                            $TrPosBCDrList['CreditAmount']    = 0;
                            $TrPosBCDrList['DeptId']          = $TransPostingData['DeptId'];
                            $TrPosBCDrList['FinId']           = $finYearId;
                            $TrPosBCDrList['TransactionDate'] = $billDate;
                            $TrPosBCDrList['ScreenSectionId'] = BANK_CHARGES_SECTION;
                            $TrPosBCDrList['DrCrId']          = DEBITID;
                            $TrPosBCDrList['TransactionGroup']= 1;
                            $TrPosBCDrList["IsDeleted"]       = DEFAULTDELETE;
                            $TrPosBCDrList["Status"]          = DEFAULTSTATUS;
                            $TrPosBCDrList["CreatedDate"]     = currentDateTime();
                            $TrPosBCDrList["CreatedBy"]       = $userId;
                            $TrPosSubBCDrList[]               = $TrPosBCDrList; 
                            //Ledger Amount Update
                            $res = $this->billing_model->update_ledger_balance($value->LedgerId,$value->BCAmount,DEBITID);
                            if(!$res){
                                $this->__error_handler('Error to Update Current Balance');
                            }
                        }
                    }

                    //Insert Rows for Debit Amount Transactions
                    $totDrAmount = 0;
                    if(!empty($DebitTransList)){ 
                        $TrPostingSubDrList = array();
                        $TrPostingDrList    = array();
                        foreach( $DebitTransList as $DrKey => $DrValues ){
                            $totDrAmount                       += $DrValues->DebitAmount;

                            $TrPostingDrList['LedgerId']        = $DrValues->LedgerId; 
                            $TrPostingDrList['PayeeId']         = $DrValues->PayeeId;
                            $TrPostingDrList['PayeeCatId']      = $DrValues->PayeeCatId;
                            $TrPostingDrList['DeptId']          = $TransPostingData['DeptId'];
                            $TrPostingDrList['FinId']           = $finYearId;
                            $TrPostingDrList['TransactionDate'] = $billDate;
                            $TrPostingDrList['TransactionNote'] = $DrValues->TransactionNote;
                            $TrPostingDrList['ScreenSectionId'] = DEBIT_SECTION;
                            $TrPostingDrList['DebitAmount']     = $DrValues->DebitAmount; 
                            $TrPostingDrList['CreditAmount']    = 0;
                            $TrPostingDrList['DrCrId']          = DEBITID;
                            $TrPostingDrList['TransactionGroup']= 1;
                            $TrPostingDrList["IsDeleted"]       = DEFAULTDELETE;
                            $TrPostingDrList["Status"]          = DEFAULTSTATUS;
                            $TrPostingDrList["CreatedDate"]     = currentDateTime();
                            $TrPostingDrList["CreatedBy"]       = $userId;
                            $TrPostingSubDrList[]               = $TrPostingDrList;
                            //Ledger Amount Update //
                            $res = $this->billing_model->update_ledger_balance($DrValues->LedgerId, $DrValues->DebitAmount, DEBITID);
                            if(!$res){
                                $this->__error_handler('Error to Update Current Balance');
                            }
                        }
                    }else{
                        $this->__error_handler('Select Debit Account');
                    }
                    
                    //Insert Rows for Deduction Amount(Credit/Debit => AS DEPAINED ON LEDGER SELECTION) Transactions 
                    if(!empty($DeductionList)){
                        
                        $TrPosSubDeductDrList = array();
                        foreach( $DeductionList as $DeductKey  => $DeductVal ){
                            
                            $totDeductionAmt                     += $DeductVal->DeductionAmount;
                            $TrPosDeductDrList['LedgerId']        = $DeductVal->LedgerId; 
                            $TrPosDeductDrList['PayeeId']         = $DeductVal->PayeeId;
                            $TrPosDeductDrList['PayeeCatId']      = $DeductVal->PayeeCatId;
                            $TrPosDeductDrList['DeptId']          = $TransPostingData['DeptId'];
                            $TrPosDeductDrList['FinId']           = $finYearId;
                            $TrPosDeductDrList['TransactionDate'] = $billDate;
                            $TrPosDeductDrList['TransactionNote'] = $DeductVal->TransactionNote;
                            $TrPosDeductDrList['DebitAmount']     = 0;
                            $TrPosDeductDrList['CreditAmount']    = $DeductVal->DeductionAmount;
                            $TrPosDeductDrList['ScreenSectionId'] = DEDUCTION_SECTION;
                            $TrPosDeductDrList['DrCrId']          = CREDITID;
                            $TrPosDeductDrList['TransactionGroup']= 1;
                            $TrPosDeductDrList['IncExpDeductionPaid'] = 2;
                            $TrPosDeductDrList["IsDeleted"]       = DEFAULTDELETE;
                            $TrPosDeductDrList["Status"]          = DEFAULTSTATUS;
                            $TrPosDeductDrList["CreatedDate"]     = currentDateTime();
                            $TrPosDeductDrList["CreatedBy"]       = $userId;
                            $TrPosSubDeductDrList[]               = $TrPosDeductDrList; 
                            //Ledger Amount Update
                            $res = $this->billing_model->update_ledger_balance($DeductVal->LedgerId,$DeductVal->DeductionAmount,CREDITID);
                            if(!$res){
                                $this->__error_handler('Error to Update Current Balance');
                            }
                        }
                    }
                      
                    //SET CREDIT AMOUNT TO OUTSTANDING LEDGER FOR BALANCEING CREDIT PORTION AS PER DEBIT SECTION
                    if(!empty($DebitTransList)){
                        $totOutstandingAmount = ((( $totDrAmount - $totAdvAmount ) - $totDeductionAmt ) + $totBankChargesAmt );
                        
                        $TrPostingSubCrList                 = array();
                        $TrPostingCrList                    = array();
                        $TrPostingCrList['LedgerId']        = $TransPostingData['OstLedgerId'];
                        $TrPostingCrList['DeptId']          = $TransPostingData['DeptId'];
                        $TrPostingCrList['FinId']           = $finYearId;
                        $TrPostingCrList['TransactionDate'] = $billDate;
                        $TrPostingCrList['ScreenSectionId'] = 0;
                        $TrPostingCrList['DebitAmount']     = 0; 
                        $TrPostingCrList['CreditAmount']    = $totOutstandingAmount;
                        $TrPostingCrList['DrCrId']          = CREDITID;
                        $TrPostingCrList['TransactionGroup']= 1;
                        $TrPostingCrList["IsDeleted"]       = DEFAULTDELETE;
                        $TrPostingCrList["Status"]          = DEFAULTSTATUS;
                        $TrPostingCrList["CreatedDate"]     = currentDateTime();
                        $TrPostingCrList["CreatedBy"]       = $userId;
                        $TrPostingSubCrList[]               = $TrPostingCrList;
                        //Ledger Amount Update 
                        $res = $this->billing_model->update_ledger_balance($TransPostingData['OstLedgerId'], $totOutstandingAmount,CREDITID);
                        if(!$res){
                           $this->__error_handler('Error to Update Current Balance');
                        }
                    }
                    
                    $data[TRANSACTION_POSTING]          = $TrPostingData;
                    $data[TRANSACTION_POSTING_ACCOUNTS] = array($TrPostingSubDrList,$TrPosSubBCDrList,$TrPostingSubAdvList,$TrPosSubDeductDrList,$TrPostingSubCrList);
                    $result  = $this->billing_model->saveTransData($data);     
                    
                    $return = array();
                    if($result){
                        $this->reset_form_session();
                        $return['success'] = 'true';
                        $return['title']   = 'Success!';
                        $return['msg']     = 'Your voucher No. is: '.$TrPosting['ConsolidateBillNo'];
                        die(json_encode($return));
                    }else{
                        $return['success'] = 'false';
                        $return['title']   = 'Error!!!';
                        $return['msg']     =  ALERT_MSG_FAIL.' '.ROLL_BACK;
                        die(json_encode($return));
                    }  
            }
            
            
        //Credit Balance/Row Insert for Balancing Double entry System
        if($TransPostingId){
            $chequeIds           = array();
            $TrChInsDetails      = array();
            $TrChInsDetailsList  = array();

            $get_bill_details    = $this->billing_model->fetchSingleBillList($TransPostingId);
            
            $OutStandingLedgerId = $get_bill_details[$TransPostingId]->OutStandingLedgerId;
            $dept_id             = $get_bill_details[$TransPostingId]->DeptId;
            $consolidate_bill_no = $get_bill_details[$TransPostingId]->ConsolidateBillNo;
            $billAmt             = $get_bill_details[$TransPostingId]->BillAmt ? $get_bill_details[$TransPostingId]->BillAmt : 0;
            $deductAmt           = $get_bill_details[$TransPostingId]->DeductAmt ? $get_bill_details[$TransPostingId]->DeductAmt : 0;
            $advanceAmt          = $get_bill_details[$TransPostingId]->AdvanceAmt ?  $get_bill_details[$TransPostingId]->AdvanceAmt : 0;
            $bankChargesAmt      = $get_bill_details[$TransPostingId]->BankChargesAmt ?  $get_bill_details[$TransPostingId]->BankChargesAmt : 0;
            $paybleAmt           = round(((($billAmt - $deductAmt) - $advanceAmt) + $bankChargesAmt),2);
            $payeeIds            = $get_bill_details[$TransPostingId]->PayeeIds; 
            
            if(!empty($CreditTransList)){
                    $totCrAmount = 0;
                    foreach($CreditTransList as $CrKey => $CreditVal ){
                        $totCrAmount                       += $CreditVal->ChequeAmount;
                        $TrPostingCrList['TransPostingId']  = $TransPostingId;
                        $TrPostingCrList['LedgerId']        = $CreditVal->LedgerId; 
                        $TrPostingCrList['DeptId']          = $dept_id;
                        $TrPostingCrList['FinId']           = $finYearId;
                        $TrPostingCrList['TransactionDate'] = dateFormat($CreditVal->DDChequeDate, 'Y-m-d');
                        $TrPostingCrList['TransactionNote'] = $CreditVal->TransactionNote;
                        $TrPostingCrList['ScreenSectionId'] = CREDIT_SECTION;
                        $TrPostingCrList['DebitAmount']     = 0;
                        $TrPostingCrList['CreditAmount']    = $CreditVal->ChequeAmount;
                        $TrPostingCrList['DrCrId']          = CREDITID;
                        $TrPostingCrList['TransactionGroup']= 2;
                        $TrPostingCrList["IsDeleted"]       = DEFAULTDELETE;
                        $TrPostingCrList["Status"]          = DEFAULTSTATUS;
                        $TrPostingCrList["CreatedDate"]     = currentDateTime();
                        $TrPostingCrList["CreatedBy"]       = $userId;
                        $TrPostingSubCrList[]               = $TrPostingCrList;
                        
                        //Ledger Amount Update
                        $res = $this->billing_model->update_ledger_balance($CreditVal->LedgerId, $CreditVal->ChequeAmount, CREDITID);
                        if(!$res){
                            $this->__error_handler('Error to Update Current Balance');
                        }
                        //Array List for Bank Chaque Update
                        array_push($chequeIds,$CreditVal->ChequeId); // insert all checkids into an array
                        //Insert data TRANSACTION_CHEQUE_DETAILS for holding cheque/DD/NEFT Transactions Records
                        $TrChInsDetails['InsId']            = $CreditVal->PayProcessModeId;
                        $TrChInsDetails['InsDate']          = dateFormat($CreditVal->DDChequeDate,'Y-m-d');
                        $TrChInsDetails['TransInsRefNo']    = $CreditVal->ChequeNumber;
                        $TrChInsDetails['DraweeRecptBank']  = $CreditVal->LedgerName;
                        $TrChInsDetails['InsModeStatusId']  = ISSUED_MODE;
                        $TrChInsDetails['PayeeName']        = $CreditVal->CPayeeName;
                        $TrChInsDetails['PayOrReceiptMode'] = 1; //1=>PAYMENT AND 2=> RECEIPT
                        $TrChInsDetails['IsChequePrinted']  = 0; //IDENTIFING CHEQUE PRINTED OR NOT
                        $TrChInsDetails["Status"]           = DEFAULTSTATUS;
                        $TrChInsDetails["IsDeleted"]        = DEFAULTDELETE;
                        $TrChInsDetails["CreatedDate"]      = currentDateTime();
                        $TrChInsDetails["CreatedBy"]        = $userId;
                        $TrChInsDetailsList[]               = $TrChInsDetails;
                    }

                    if( $paybleAmt != round($totCrAmount,2) ){
                        $this->__error_handler('Your Cheque Amount Does Not Match With Debit Amount'); 
                    }

                    //SET CREDIT AMOUNT TO OUTSTANDING LEDGER FOR BALANCEING DEBIT PORTION AS PER PREVIOUS CREDIT SECTION
                    if(!empty($CreditTransList)){
                        $TrPostingSubDrList = array();
                        $TrPostingDrList    = array();
                        $TrPostingDrList['LedgerId']        = $OutStandingLedgerId;
                        $TrPostingDrList['DeptId']          = $dept_id;
                        $TrPostingDrList['FinId']           = $finYearId;
                        $TrPostingDrList['TransactionDate'] = dateFormat($CreditVal->DDChequeDate,'Y-m-d');
                        $TrPostingDrList['ScreenSectionId'] = 0;
                        $TrPostingDrList['DebitAmount']     = $totCrAmount; 
                        $TrPostingDrList['CreditAmount']    = 0;
                        $TrPostingDrList['DrCrId']          = DEBITID;
                        $TrPostingDrList['TransactionGroup']= 2;
                        $TrPostingDrList["IsDeleted"]       = DEFAULTDELETE;
                        $TrPostingDrList["Status"]          = DEFAULTSTATUS;
                        $TrPostingDrList["CreatedDate"]     = currentDateTime();
                        $TrPostingDrList["CreatedBy"]       = $userId;
                        $TrPostingSubDrList[]               = $TrPostingDrList;
                        //Ledger Amount Update 
                        $res = $this->billing_model->update_ledger_balance($OutStandingLedgerId, $totCrAmount, DEBITID);
                        if(!$res){
                            $this->__error_handler('Error to Update Current Balance');
                        }
                    }
                    
                    //Update IsPartialPost => 0 On TRANSACTION_POSTING Table for Identify Complete Transaction// 
                    $TrPostingUpdate = array();
                    $TrPostingUpdate['BillDate']        = $billDate;
                    $TrPostingUpdate['IsPartialPost']   = 0;

                    $chequeIds                          = array_unique( array_filter( array_values($chequeIds) ) );
                    $data[TRANSACTION_POSTING]          = $TrPostingUpdate;
                    $data[TRANSACTION_POSTING_ACCOUNTS] = array($TrPostingSubCrList,$TrPostingSubDrList);

                    $result = $this->billing_model->saveTransData($data,$TrChInsDetailsList,$chequeIds,$TransPostingId);
                    if($result){
                        $this->reset_form_session();
                        $return['success'] = 'true';
                        $return['title']   = 'Success!';
                        $return['msg']     = 'Your voucher No. is: '.$consolidate_bill_no;
                        die(json_encode($return));
                    }else{
                        $return['success'] = 'false';
                        $return['title']   = 'Error!!!';
                        $return['msg']     =  ALERT_MSG_FAIL.' '.ROLL_BACK;
                        die(json_encode($return));
                    }  
                }else{
                    $this->__error_handler('No Credit Row in Cheque Section');
                }
            }
        }
        $this->render();
    }
    
    
    
    //----------------------EDIT INHOUSE BILL----------------------------------------//
    
    public function nonproject_inhouse_edit(){
        
        $userId = $_SESSION["tu_finance_user"]->UserId;
        $this->setViewData('title', "INHOUSE BILL");
        $this->setViewData("departmentList", $this->makingArray($this->billing_model->getTableDetails(MST_DEPARTMENT, $deptCondition = array("OrgBranchId" => ORGBRANCHID)), "DeptId", "DeptName"));
        $this->setViewData("voucherList", $this->billing_model->getTableDetails(MST_VOUCHER_TYPE, array("VoucherTypeId" => array(ADVACE, ADJUSTMENT, FINAL_VOUCHER))));
        $this->setViewData("budgetHeadList", $this->billing_model->getTableDetails(MST_BUDGET_HEAD, array("BudgetCategoryId"=>array(1,2))));
        $this->setViewData("sundryCreditorsLedgerList", $this->billing_model->getTableDetails(MST_GROUP_LEDGER, array("LedgerId" => 123)));
        $this->setViewData("creditAccLedgerList", $this->billing_model->fetchAllBankLedger());
        $this->setViewData("advAccLedgerList", $this->billing_model->fetch_ledger_order_wise(array(ASSETLEDGER,EXPENSELEDGER),array('IsAdvanceInhouse'=>1),array('IsAdvanceInhouse'=>1)));
        $this->setViewData("payeeList", $this->billing_model->get_payee_list(array(CAT_STUDENT,CAT_EMPLOYEE,CAT_DONOR,CAT_OTHER)));
        $this->setViewData("deductionAccountLedgerList", $this->billing_model->fetch_ledger_order_wise(array(INCOMELEDGER,LIABILITYLEDGER,ASSETLEDGER,EXPENSELEDGER),array('IsDeduction'=>1),array('IsDeduction'=>1)));
        $this->setViewData("chequeList", $this->billing_model->getTableDetails(MST_BANK_CHEQUE_DETAILS,$chequeCondition = array("InsModeStatusId" => array(NOT_ISSUED_MODE))));
        $this->setViewData("bankAccountList", $this->billing_model->getTableDetails(MST_BANK_ACCOUNT));
        $this->setViewData("instrumentList", $this->billing_model->getTableDetails(MST_INSTRUMENT));
        $this->setViewData("bankChargesLedgerList",$this->billing_model->fetch_ledger_order_wise(array(EXPENSELEDGER),array('IsBankCharge' => 1),array('IsBankCharge' => 1)));
        
        //-----------------TRANSPOSTING ID-----------------------//
        $TransPostingId = ci_decode($this->uri->segment(3) ? $this->uri->segment(3) : NULL);
        //-----------------TRANSPOSTING ID-----------------------//

        //---------------DATA FETCH FOR EDIT-----------------------//
        $deleted_ids = array();
        $user_id    = $_SESSION["tu_finance_user"]->UserId;
        $cur_status = $_SESSION['BILL_EDIT_NPI'] += 1;
        
        if($cur_status == 1){
            $pst_data_list =  $this->billing_model->getTableDetails(TRANSACTION_POSTING, array('TransPostingId' => $TransPostingId, 'IsDeleted' => 0));
            $dr_data_list  =  $this->billing_model->getTableDetails(VIEW_TRANSACTION_POSTING_ACCOUNTS_DETAILS, array('TransPostingId' => $TransPostingId, 'IsDeleted' => 0,'ScreenSectionId'=>DEBIT_SECTION));
            $adv_data_list =  $this->billing_model->getTableDetails(VIEW_TRANSACTION_POSTING_ACCOUNTS_DETAILS, array('TransPostingId' => $TransPostingId, 'IsDeleted' => 0,'ScreenSectionId'=>ADVANCE_SECTION));
            $di_data_list  =  $this->billing_model->getTableDetails(VIEW_TRANSACTION_POSTING_ACCOUNTS_DETAILS, array('TransPostingId' => $TransPostingId, 'IsDeleted' => 0,'ScreenSectionId'=>DEDUCTION_SECTION));
            $bc_data_list  =  $this->billing_model->getTableDetails(VIEW_TRANSACTION_POSTING_ACCOUNTS_DETAILS, array('TransPostingId' => $TransPostingId, 'IsDeleted' => 0,'ScreenSectionId'=>BANK_CHARGES_SECTION));  
            $cr_data_list  =  $this->billing_model->getTableDetails(VIEW_TRANSACTION_POSTING_ACCOUNTS_DETAILS, array('TransPostingId' => $TransPostingId, 'IsDeleted' => 0,'ScreenSectionId'=>CREDIT_SECTION));
            $ost_data_list =  $this->billing_model->getTableDetails(VIEW_TRANSACTION_POSTING_ACCOUNTS_DETAILS, array('TransPostingId' => $TransPostingId, 'IsDeleted' => 0,'ScreenSectionId'=>OUTSTANDING_SECTION));

            //-------------|| RE-STORE DATA IN SESSION FOR BILL MODIFICATION ||---------------//
            
            //-------STORE DEBIT DATA LIST--------//
            foreach($dr_data_list as $dr_val){
                    $SessionSL = strtotime("now").rand(100, 999).$user_id;
                    $drDataList = array( 
                                   'SessionSL'       => $SessionSL,
                                   'DeptId'          => $dr_val->DeptId,
                                   'LedgerId'        => $dr_val->LedgerId,
                                   'LedgerName'      => $dr_val->LedgerName,
                                   'PayeeId'         => $dr_val->PayeeId,
                                   'PayeeCatId'      => $dr_val->PayeeCatId,
                                   'PayeeName'       => GetPayeeName($dr_val->PayeeCatId, $dr_val->PayeeId),
                                   'TransactionNote' => $dr_val->TransactionNote,
                                   'DebitAmount'     => $dr_val->DebitAmount );

                    $_SESSION['ADD_TO_DR_LIST_NPI'][$SessionSL] = (object) $drDataList;
                    
                    $deleted_ids[] = array('TransPostingACId' => $dr_val->TransPostingACId, 'IsDeleted'=>1,'Status'=>0 );
            }
            //-------END STORE DEBIT DATA LIST--------//

            //-------STORE ADVANCE DATA LIST-----------//
            foreach($adv_data_list as $adv_val){

                    $SessionSL = strtotime("now").rand(100, 999).$user_id;
                    $advDataList = array( 
                                   'SessionSL'       => $SessionSL,
                                   'LedgerId'        => $adv_val->LedgerId,
                                   'LedgerName'      => $adv_val->LedgerName,
                                   'PayeeId'         => $adv_val->PayeeId,
                                   'PayeeCatId'      => $adv_val->PayeeCatId,
                                   'PayeeName'       => GetPayeeName($adv_val->PayeeCatId, $adv_val->PayeeId),
                                   'TransactionNote' => $adv_val->TransactionNote,
                                   'AdvanceAmount'   => $adv_val->CreditAmount );

                    $_SESSION['ADD_TO_ADVANCE_LIST_NPI'][$SessionSL] = (object) $advDataList;
                    
                    $deleted_ids[] = array('TransPostingACId' => $adv_val->TransPostingACId, 'IsDeleted'=>1,'Status'=>0 );
            }
            //-------END STORE ADVANCE DATA LIST-------//

            //-------STORE DEDUCTION DATA LIST-----------//
            foreach($di_data_list as $di_val){
                    $SessionSL = strtotime("now").rand(100, 999).$user_id;
                    $diDataList = array( 
                                   'SessionSL'       => $SessionSL,
                                   'LedgerId'        => $di_val->LedgerId,
                                   'LedgerName'      => $di_val->LedgerName,
                                   'PayeeId'         => $di_val->PayeeId,
                                   'PayeeCatId'      => $di_val->PayeeCatId,
                                   'PayeeName'       => GetPayeeName($di_val->PayeeCatId, $di_val->PayeeId),
                                   'TransactionNote' => $di_val->TransactionNote,
                                   'DeductionAmount' => $di_val->CreditAmount );

                    $_SESSION['ADD_TO_DEDUCTION_LIST_NPI'][$SessionSL] = (object) $diDataList;
                    
                    $deleted_ids[] = array('TransPostingACId' => $di_val->TransPostingACId, 'IsDeleted'=>1,'Status'=>0 );
            }
            //-------END STORE DEDUCTION DATA LIST-----------//

            //-------STORE BANK CHARGES DATA LIST-----------//
            foreach($bc_data_list as $bc_val){
                    $SessionSL = strtotime("now").rand(100, 999).$user_id;
                    $bcDataList = array( 
                                   'SessionSL'       => $SessionSL,
                                   'LedgerId'        => $bc_val->LedgerId,
                                   'LedgerName'      => $bc_val->LedgerName,
                                   'TransactionNote' => $bc_val->TransactionNote,
                                   'BCAmount'        => $bc_val->DebitAmount );

                    $_SESSION['ADD_BANK_CHARGES_NPI'][$SessionSL] = (object) $bcDataList;
                    
                    $deleted_ids[] = array('TransPostingACId' => $bc_val->TransPostingACId, 'IsDeleted'=>1,'Status'=>0 );
            }
            //-------END STORE BANK CHARGES DATA LIST-------//

            //-------STORE CREDIT DATA LIST-----------//
            if($pst_data_list[0]->IsBilled == 1){
                foreach($cr_data_list as $cr_val){
                    $SessionSL = strtotime("now").rand(100, 999).$user_id;
                    $crDataList = array( 
                                   'SessionSL'       => $SessionSL,
                                   'LedgerId'        => $cr_val->LedgerId,
                                   'LedgerName'      => $cr_val->LedgerName,
                                   'ChequeId'        => GetChequeId(GetChequeNo($cr_val->TransPostingACId)),
                                   'ChequeNumber'    => GetChequeNo($cr_val->TransPostingACId),
                                   'CPayeeName'      => get_cheque_payee_name($cr_val->TransPostingACId),
                                   'TransactionNote' => $cr_val->TransactionNote,
                                   'ChequeAmount'    => $cr_val->CreditAmount );
                    $_SESSION['ADD_TO_CR_LIST_NPI'][$SessionSL] = (object) $crDataList;
                    
                    $deleted_ids[] =  array('TransPostingACId' => $cr_val->TransPostingACId, 'IsDeleted'=>1,'Status'=>0 );
                }  
            }
            //-------END STORE CREDIT DATA LIST-------//
            
            //-------STORE OUTSTANDING DATA LIST------//
            foreach( $ost_data_list as $ost_val){
                $deleted_ids[] =  array('TransPostingACId' => $ost_val->TransPostingACId, 'IsDeleted'=>1,'Status'=>0 );
            }  
            //-------END STORE CREDIT DATA LIST-------//
            
            //-------STORE POSTING DATA--------//
            $_SESSION['TRANS_POSTING_NPI'] = end($pst_data_list);      
            //-------END STORE POSTING DATA----//

            //-------STORE WHICH IDS WILL BE DELETE--------//
                $_SESSION['IS_DEL_NPI'] = (object) $deleted_ids;
            //-------STORE WHICH IDS WILL BE DELETE--------//
            }
            
        //---------------------------------------BILL EDIT---------------------------------------//
        $TransPostingData        = (array) @$_SESSION['TRANS_POSTING_NPI']; 
        $DebitTransList          = (array) @$_SESSION['ADD_TO_DR_LIST_NPI'];
        $DeductionList           = (array) @$_SESSION['ADD_TO_DEDUCTION_LIST_NPI'];
        $CreditTransList         = (array) @$_SESSION['ADD_TO_CR_LIST_NPI'];
        $AdvanceTransList        = (array) @$_SESSION['ADD_TO_ADVANCE_LIST_NPI'];
        $BankChargesData         = (array) @$_SESSION['ADD_BANK_CHARGES_NPI'];
        $TrPosting               = array();
        $TrPostingSubDrList      = array();
        $TrPostingSubCrList      = array();
        $TrPosSubDeductDrList    = array();
        
       
        $billDate                = dateFormat($TransPostingData['BillDate'], 'Y-m-d'); 
        $finYearId               = getFinId($billDate ? $billDate : currentDateTime('Y-m-d'));
        
        if($this->input->post("_action") == 'saveNPI'){ 
            //This section is block to save data when transaction date is out of Financial Year
            if(fin_lock($finYearId)){
                $this->__error_handler('This Financial Year Already Closed!!!');  
            }
            
            $TransPostingId = $this->input->post("TransPostingId");
            //Particular Data Set Array to Submit On  TRANSACTION_POSTING Table (Define for This Particular Window)
            $TrPostingData = array();
            
            if( $TransPostingId != NULL ){
                
                $TrPosting['FinId']               = $finYearId; 
                $TrPosting['OrgBranchId']         = default_organization_branch();
                $TrPosting['BillingTypeId']       = 1;//This is identyfy to Posting Screen//TU_BILLING_TYPE_MASTER->cofiguration Table
                $TrPosting['NoteDate']            = dateFormat($TransPostingData['NoteDate'],'Y-m-d');
                $TrPosting['OrderNo']             = $TransPostingData['OrderNo'];
                $TrPosting['InvoiceNo']           = $TransPostingData['InvoiceNo'];
                $TrPosting['BillDate']            = $billDate;
                $TrPosting['TransDate']           = $billDate;
                $TrPosting['OrderDate']           = dateFormat($TransPostingData['OrderDate'],'Y-m-d');
                $TrPosting['TokenNo']             = $TransPostingData['TokenNo'];
                $TrPosting['InvoiceDate']         = dateFormat($TransPostingData['InvoiceDate'], 'Y-m-d');
                $TrPosting['ApproveDate']         = dateFormat($TransPostingData['ApproveDate'], 'Y-m-d');
                $TrPosting['FileRefNo']           = $TransPostingData['FileRefNo'];
                $TrPosting['VoucherTypeId']       = $TransPostingData['VoucherTypeId'];
                $TrPosting['IsBilled']            = $TransPostingData['IsBilled'];
                $TrPosting['Narration']           = strtoupper($TransPostingData['Narration']);
                $TrPosting['BudgetHeadId']        = $TransPostingData['BudgetHeadId'];
                $TrPosting['BankAcRefBySection']  = $TransPostingData['BankAcRefBySection'];
                $TrPostingData                    = array_merge($TrPosting, $this->defaultData($TransPostingId));//Set Default Coulumn(6C) Value of Table
                
                    //SERVER SIDE VALIDATION
                    $valid_field_array = array(
                        array('field'=>'NoteDate','msg'=>'Note Date Is Required'),
                        array('field'=>'VoucherTypeId','msg'=>'Voucher Type Is Required'),
                        array('field'=>'BudgetHeadId','msg'=>'Budget Category Is Required')
                    );

                    $this->valid_post_data($TrPostingData,$valid_field_array);

                    //Insert Rows for Adjustment for Advance Amount Transactions
                    $totAdvAmount = 0;
                    if( $TrPosting['VoucherTypeId'] == ADJUSTMENT ){
                        
                        if(!empty($AdvanceTransList)){ 
                            $TrPostingSubAdvList = array();
                            $TrPostingAdvList    = array();
                            
                            foreach( $AdvanceTransList as $advKey => $advValues ){
                                
                                $totAdvAmount                        += $advValues->AdvanceAmount;
                                $TrPostingAdvList['LedgerId']         = $advValues->LedgerId; 
                                $TrPostingAdvList['PayeeId']          = $advValues->PayeeId;
                                $TrPostingAdvList['PayeeCatId']       = $advValues->PayeeCatId;
                                $TrPostingAdvList['DeptId']           = $TransPostingData['DeptId'];
                                $TrPostingAdvList['FinId']            = $finYearId;
                                $TrPostingAdvList['TransactionDate']  = $billDate;
                                $TrPostingAdvList['TransactionNote']  = $advValues->TransactionNote;
                                $TrPostingAdvList['ScreenSectionId']  = ADVANCE_SECTION;
                                $TrPostingAdvList['DebitAmount']      = 0;
                                $TrPostingAdvList['CreditAmount']     = $advValues->AdvanceAmount;
                                $TrPostingAdvList['DrCrId']           = CREDITID;
                                $TrPostingAdvList['TransactionGroup'] = 1;
                                $TrPostingAdvList["IsDeleted"]        = DEFAULTDELETE;
                                $TrPostingAdvList["Status"]           = DEFAULTSTATUS;
                                $TrPostingAdvList["CreatedDate"]      = currentDateTime();
                                $TrPostingAdvList["CreatedBy"]        = $userId;
                                $TrPostingSubAdvList[]                = $TrPostingAdvList; 
                               
                            }
                        }else{
                           $this->__error_handler('Enter Advance Bill');
                        }
                    }
                    
                    //Insert Rows for Bank Charges Amount(Debit) Transactions 
                    $totBankChargesAmt = 0;
                    if(!empty($BankChargesData)){
                        
                        foreach($BankChargesData as $key => $value){
                            $TrPosBCDrList                    = array();
                            $TrPosSubBCDrList                 = array();
                            $totBankChargesAmt               += $value->BCAmount;
                            
                            $TrPosBCDrList['LedgerId']        = $value->LedgerId;
                            $TrPosBCDrList['DebitAmount']     = $value->BCAmount;
                            $TrPosBCDrList['CreditAmount']    = 0;
                            $TrPosBCDrList['DeptId']          = $TransPostingData['DeptId'];
                            $TrPosBCDrList['FinId']           = $finYearId;
                            $TrPosBCDrList['TransactionDate'] = $billDate;
                            $TrPosBCDrList['ScreenSectionId'] = BANK_CHARGES_SECTION;
                            $TrPosBCDrList['DrCrId']          = DEBITID;
                            $TrPosBCDrList['TransactionGroup']= 1;
                            $TrPosBCDrList["IsDeleted"]       = DEFAULTDELETE;
                            $TrPosBCDrList["Status"]          = DEFAULTSTATUS;
                            $TrPosBCDrList["CreatedDate"]     = currentDateTime();
                            $TrPosBCDrList["CreatedBy"]       = $userId;
                            $TrPosSubBCDrList[]               = $TrPosBCDrList;  
                        }
                    }

                    //Insert Rows for Debit Amount Transactions
                    $totDrAmount = 0;
                    if(!empty($DebitTransList)){ 
                        $TrPostingSubDrList = array();
                        $TrPostingDrList    = array();
                        foreach( $DebitTransList as $DrKey => $DrValues ){
                            $totDrAmount                       += $DrValues->DebitAmount;
                            $TrPostingDrList['LedgerId']        = $DrValues->LedgerId; 
                            $TrPostingDrList['PayeeId']         = $DrValues->PayeeId;
                            $TrPostingDrList['PayeeCatId']      = $DrValues->PayeeCatId;
                            $TrPostingDrList['DeptId']          = $TransPostingData['DeptId'];
                            $TrPostingDrList['FinId']           = $finYearId;//remarks to bhaskar
                            $TrPostingDrList['TransactionDate'] = $billDate;//remarks to bhaskar
                            $TrPostingDrList['TransactionNote'] = $DrValues->TransactionNote;
                            $TrPostingDrList['ScreenSectionId'] = DEBIT_SECTION;
                            $TrPostingDrList['DebitAmount']     = $DrValues->DebitAmount; 
                            $TrPostingDrList['CreditAmount']    = 0;
                            $TrPostingDrList['DrCrId']          = DEBITID;
                            $TrPostingDrList['TransactionGroup']= 1;
                            $TrPostingDrList["IsDeleted"]       = DEFAULTDELETE;
                            $TrPostingDrList["Status"]          = DEFAULTSTATUS;
                            $TrPostingDrList["CreatedDate"]     = currentDateTime();
                            $TrPostingDrList["CreatedBy"]       = $userId;
                            $TrPostingSubDrList[]               = $TrPostingDrList;
                           
                        }
                    }else{
                        $this->__error_handler('Select Debit Account');
                    }
                    
                    //Insert Rows for Deduction Amount(Credit/Debit => AS DEPAINED ON LEDGER SELECTION) Transactions 
                    $totDeductionAmt = 0;
                    if(!empty($DeductionList)){
                        $TrPosSubDeductDrList = array();
                        foreach( $DeductionList as $DeductKey  => $DeductVal ){
                            
                            $totDeductionAmt                     += $DeductVal->DeductionAmount;
                            $TrPosDeductDrList['LedgerId']        = $DeductVal->LedgerId; 
                            $TrPosDeductDrList['PayeeId']         = $DeductVal->PayeeId;
                            $TrPosDeductDrList['PayeeCatId']      = $DeductVal->PayeeCatId;
                            $TrPosDeductDrList['DeptId']          = $TransPostingData['DeptId'];
                            $TrPosDeductDrList['FinId']           = $finYearId;//remarks to bhaskar
                            $TrPosDeductDrList['TransactionDate'] = $billDate;//remarks to bhaskar
                            $TrPosDeductDrList['TransactionNote'] = $DeductVal->TransactionNote;
                            $TrPosDeductDrList['DebitAmount']     = 0;
                            $TrPosDeductDrList['CreditAmount']    = $DeductVal->DeductionAmount;
                            $TrPosDeductDrList['ScreenSectionId'] = DEDUCTION_SECTION;
                            $TrPosDeductDrList['DrCrId']          = CREDITID;
                            $TrPosDeductDrList['TransactionGroup']= 1;
                            $TrPosDeductDrList['IncExpDeductionPaid'] = 2;
                            $TrPosDeductDrList["IsDeleted"]       = DEFAULTDELETE;
                            $TrPosDeductDrList["Status"]          = DEFAULTSTATUS;
                            $TrPosDeductDrList["CreatedDate"]     = currentDateTime();
                            $TrPosDeductDrList["CreatedBy"]       = $userId;
                            $TrPosSubDeductDrList[]               = $TrPosDeductDrList; 

                        }
                    }
                      
                    //SET CREDIT AMOUNT TO OUTSTANDING LEDGER FOR BALANCEING CREDIT PORTION AS PER DEBIT SECTION
                    $totOutstandingAmount = 0;
                    if(!empty($DebitTransList)){
                        $totOutstandingAmount = ((( $totDrAmount - $totAdvAmount ) - $totDeductionAmt ) + $totBankChargesAmt );
                        
                        $TrPostingSubCrList = array();
                        $TrPostingCrList    = array();
                        $TrPostingCrList['LedgerId']        = $TransPostingData['OstLedgerId'];
                        $TrPostingCrList['DeptId']          = $TransPostingData['DeptId'];
                        $TrPostingCrList['FinId']           = $finYearId;//remarks to bhaskar
                        $TrPostingCrList['TransactionDate'] = $billDate;//remarks to bhaskar
                        $TrPostingCrList['ScreenSectionId'] = 0;
                        $TrPostingCrList['DebitAmount']     = 0; 
                        $TrPostingCrList['CreditAmount']    = $totOutstandingAmount;
                        $TrPostingCrList['DrCrId']          = CREDITID;
                        $TrPostingCrList['TransactionGroup']= 1;
                        $TrPostingCrList["IsDeleted"]       = DEFAULTDELETE;
                        $TrPostingCrList["Status"]          = DEFAULTSTATUS;
                        $TrPostingCrList["CreatedDate"]     = currentDateTime();
                        $TrPostingCrList["CreatedBy"]       = $userId;
                        $TrPostingSubCrList[]               = $TrPostingCrList;
                       
                    }
                    
            $totCrcount = 0;      
            if(!empty($CreditTransList)){
                foreach($CreditTransList as $cont ){
                   $totCrcount ++;
                }
            }
                    
            if( $totCrcount == 0 ){
                if(chk_parcial_post($TransPostingId)){
                    echo 'chk_partial_post'; die; 
                }else{
                    $bill_sec_data[TRANSACTION_POSTING] = $TrPostingData;
                    $bill_sec_data[TRANSACTION_POSTING_ACCOUNTS] = array($TrPostingSubDrList,$TrPosSubBCDrList,$TrPostingSubAdvList,$TrPosSubDeductDrList,$TrPostingSubCrList);
                    $result  = $this->billing_model->saveTransData($bill_sec_data,NULL,NULL,$TransPostingId);
                    //delete previous cheque list//
                    $this->billing_model->del_trans_ac_data($_SESSION['IS_DEL_NPI']);
                    $this->billing_model->del_trans_chk_data($_SESSION['IS_DEL_NPI']);
                    $this->reset_form_session();
                }        
            }
                    
           
                    
        if( count($CreditTransList) == 0 ){
            $return = array();
            if($result){
                echo 'Your Voucher No. Is: '.GetBillNo($TransPostingId); die;
            }else{
                $return['success'] = 'false';
                $return['title']   = 'Error!!!';
                $return['msg']     =  ALERT_MSG_FAIL.' '.ROLL_BACK;
                die(json_encode($return));
            } 
        }
                    
        //Credit Balance/Row Insert for Balancing Double entry System  
        if( count($CreditTransList) >= 1 ){
            
            $chequeIds           = array();
            $TrChInsDetails      = array();
            $TrChInsDetailsList  = array();

            $get_bill_details    = $this->billing_model->fetchSingleBillList($TransPostingId);
            $OutStandingLedgerId = $get_bill_details[$TransPostingId]->OutStandingLedgerId;
            $dept_id             = $get_bill_details[$TransPostingId]->DeptId;
            $consolidate_bill_no = $get_bill_details[$TransPostingId]->ConsolidateBillNo;
           
            if(!empty($CreditTransList)){
                    $TrPostingSubCrListChk = array();
                    $TrPostingCrListChk = array();
                    
                    $totCrAmount = 0;
                    foreach($CreditTransList as $CrKey => $CreditVal ){
                       
                        $totCrAmount                          += $CreditVal->ChequeAmount;
                        $TrPostingCrListChk['TransPostingId']  = $TransPostingId;
                        $TrPostingCrListChk['LedgerId']        = $CreditVal->LedgerId; 
                        $TrPostingCrListChk['DeptId']          = $dept_id;
                        $TrPostingCrListChk['FinId']           = $finYearId;
                        $TrPostingCrListChk['TransactionDate'] = dateFormat($CreditVal->DDChequeDate,'Y-m-d');
                        $TrPostingCrListChk['TransactionNote'] = $CreditVal->TransactionNote;
                        $TrPostingCrListChk['ScreenSectionId'] = CREDIT_SECTION;
                        $TrPostingCrListChk['DebitAmount']     = 0;
                        $TrPostingCrListChk['CreditAmount']    = $CreditVal->ChequeAmount;
                        $TrPostingCrListChk['DrCrId']          = CREDITID;
                        $TrPostingCrListChk['TransactionGroup']= 2;
                        $TrPostingCrListChk["IsDeleted"]       = DEFAULTDELETE;
                        $TrPostingCrListChk["Status"]          = DEFAULTSTATUS;
                        $TrPostingCrListChk["CreatedDate"]     = currentDateTime();
                        $TrPostingCrListChk["CreatedBy"]       = $userId;
                        $TrPostingSubCrListChk[]               = $TrPostingCrListChk;
                        
                        //Array List for Bank Chaque Update
                        array_push( $chequeIds, $CreditVal->ChequeId ); //insert all checkids into an array
                        //Insert data TRANSACTION_CHEQUE_DETAILS for holding cheque/DD/NEFT Transactions Records
                        $TrChInsDetails['InsId']            = $CreditVal->PayProcessModeId;
                        $TrChInsDetails['InsDate']          = dateFormat($CreditVal->DDChequeDate,'Y-m-d');
                        $TrChInsDetails['TransInsRefNo']    = $CreditVal->ChequeNumber;
                        $TrChInsDetails['DraweeRecptBank']  = $CreditVal->LedgerName;
                        $TrChInsDetails['InsModeStatusId']  = ISSUED_MODE;
                        $TrChInsDetails['PayeeName']        = $CreditVal->CPayeeName;
                        $TrChInsDetails['PayOrReceiptMode'] = 1; //1=>PAYMENT AND 2=> RECEIPT
                        $TrChInsDetails['IsChequePrinted']  = 0; //IDENTIFING CHEQUE PRINTED OR NOT
                        $TrChInsDetails["Status"]           = DEFAULTSTATUS;
                        $TrChInsDetails["IsDeleted"]        = DEFAULTDELETE;
                        $TrChInsDetails["CreatedDate"]      = currentDateTime();
                        $TrChInsDetails["CreatedBy"]        = $userId;
                        $TrChInsDetailsList[]               = $TrChInsDetails;
                    }
                  
                    if( $totOutstandingAmount != round($totCrAmount,2) ){
                        $this->__error_handler('Your Cheque Amount Does Not Match With Debit Amount'); 
                    }

                    //SET CREDIT AMOUNT TO OUTSTANDING LEDGER FOR BALANCEING DEBIT PORTION AS PER PREVIOUS CREDIT SECTION
                    if(!empty($CreditTransList)){
                        
                        $TrPostingSubDrListChk = array();
                        $TrPostingDrListChk    = array();
                        
                        $TrPostingDrListChk['LedgerId']        = $OutStandingLedgerId;
                        $TrPostingDrListChk['DeptId']          = $dept_id;
                        $TrPostingDrListChk['FinId']           = $finYearId;//remarks to bhaskar
                        $TrPostingDrListChk['TransactionDate'] = dateFormat($CreditVal->DDChequeDate,'Y-m-d');
                        $TrPostingDrListChk['ScreenSectionId'] = 0;
                        $TrPostingDrListChk['DebitAmount']     = $totCrAmount; 
                        $TrPostingDrListChk['CreditAmount']    = 0;
                        $TrPostingDrListChk['DrCrId']          = DEBITID;
                        $TrPostingDrListChk['TransactionGroup']= 2;
                        $TrPostingDrListChk["IsDeleted"]       = DEFAULTDELETE;
                        $TrPostingDrListChk["Status"]          = DEFAULTSTATUS;
                        $TrPostingDrListChk["CreatedDate"]     = currentDateTime();
                        $TrPostingDrListChk["CreatedBy"]       = $userId;
                        $TrPostingSubDrListChk[]               = $TrPostingDrListChk;
                    }
                    
                    
                    //---------------------------------UPPER SECTION--------------------------------------------//
                    $bill_sec_data[TRANSACTION_POSTING] = $TrPostingData;
                    $bill_sec_data[TRANSACTION_POSTING_ACCOUNTS] = array($TrPostingSubDrList,$TrPosSubBCDrList,$TrPostingSubAdvList,$TrPosSubDeductDrList,$TrPostingSubCrList);
                    $this->billing_model->saveTransData($bill_sec_data,NULL,NULL,$TransPostingId);
                    //---------------------------------END UPPER SECTION--------------------------------------------//
                    

                    //Update IsPartialPost => 0 On TRANSACTION_POSTING Table for Identify Complete Transaction// 
                    $TrPostingUpdate = array();
                    $TrPostingUpdate['BillDate']      = $billDate;
                    $TrPostingUpdate['IsPartialPost'] = 0;
                   
                    $chequeIds = array_unique( array_filter( array_values($chequeIds) ) );
                    $cheque_sec_data[TRANSACTION_POSTING] = $TrPostingUpdate;
                    $cheque_sec_data[TRANSACTION_POSTING_ACCOUNTS] = array($TrPostingSubCrListChk,$TrPostingSubDrListChk);
                   
                    $result = $this->billing_model->saveTransData($cheque_sec_data, $TrChInsDetailsList, $chequeIds, $TransPostingId);
                    if($result){
                        $this->billing_model->del_trans_ac_data($_SESSION['IS_DEL_NPI']);
                        $this->billing_model->del_trans_chk_data($_SESSION['IS_DEL_NPI']);
                        $this->reset_form_session();
                        echo 'Your voucher No. is: '.$consolidate_bill_no; die;
                    }else{
                        $return['success'] = 'false';
                        $return['title']   = 'Error!!!';
                        $return['msg']     =  ALERT_MSG_FAIL.' '.ROLL_BACK;
                        die(json_encode($return));
                    }  
                }
            }
        }
        
        }
        $this->render();   
    }

    //----THIS IS USED TO UNSET SESSION VARIABLE FROM THIS PARTICULAR FROM/PAGE----//
    public function reset_form_session(){
        
        $_SESSION['BILL_EDIT_NPI'] = 0;
        unset($_SESSION['IS_DEL_NPI']);
        
        unset($_SESSION['TRANS_POSTING_NPI']);
        unset($_SESSION['ADD_TO_DR_LIST_NPI']);
        unset($_SESSION['ADD_TO_CR_LIST_NPI']);
        unset($_SESSION['ADD_TO_DEDUCTION_LIST_NPI']);
        unset($_SESSION['ADD_TO_ADVANCE_LIST_NPI']);
        unset($_SESSION['ADVANCE_BILL_DETAILS_NPI']);
        unset($_SESSION['ADD_BANK_CHARGES_NPI']);
        
    }

}
