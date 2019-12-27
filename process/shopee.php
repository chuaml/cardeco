<?php 
require('inc/class/CSV_Table_Handler.php');
require('inc/class/Shopee_Orders.php');
require('inc/class/Shopee_Product_Info.php');
require('inc/class/Stock_Items_Getter.php');
require('inc/class/Stock_Items_Checker.php');
require('inc/class/Orders_Result_Formatter.php');
require('inc/class/Stock_Items_Conclusion.php');

$Shopee = new Shopee_Orders($_FILES['file_dataFile']);
$Stock_Getter = new Stock_Items_Getter();
$Checker = new Stock_Items_Checker(new Shopee_Product_Info);
$Formatter = null;
$Conclusion = new Stock_Items_Conclusion(new Shopee_Product_Info);



try{
	$Shopee->gatherItems_Code();
	$items_code = $Shopee->getItems_Code();
	
	
	$orders_list = $Shopee->getOrders_List();

	$Stock_Getter->setItems_Code($items_code);
	$stock_items = $Stock_Getter->getStock_Items($con);


	$Checker->setItems_Code($orders_list);
	$Checker->setResult($stock_items);

	$Conclusion->setOrders_List($orders_list);
	$Conclusion->setResult($stock_items);


	$output = $Checker->getOutput();


$Formatter = new Orders_Result_Formatter($output);


}catch(Exception $e){
	echo 'Error, no result matched.';
	throw $e;
}
