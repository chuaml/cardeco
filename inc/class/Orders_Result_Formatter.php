<?php 
class Orders_Result_Formatter{

	private $output;


	public function __construct(array $output){
		if(!is_array($output)){throw new Exception('output should contain arrays');}
		$this->output = $output;
	}

	public function getOutofStock(){
		return $this->formatResult('outofstock');
	}

	public function getItemList(){
		return $this->formatResult('itemList');
	}

	public function getNotFound(){
		return $this->formatResult('notfound');
	}

	public function getConclusion(array $conclusion){
		$keys = array_keys($conclusion[0]);
		$result = '<table><thead><tr><th>' 
		.implode('</th><th>', $keys) 
		.'</th></tr><tbody>';
		foreach($conclusion as $r){
			$result .= '<tr><td>' .implode('</td><td>', $r) .'</td></tr>';
		}
		$result .= '</tbody></table>';

		return $result;
	}

	private function formatResult(string $output_type){
		$keys = array_keys($this->output[$output_type][0]);
		$result = '<table><thead><tr><th>' 
		.implode('</th><th>', $keys) 
		.'</th></tr><tbody>';
		foreach($this->output[$output_type] as $r){
			$result .= '<tr><td>' .implode('</td><td>', $r) .'</td></tr>';
		}
		$result .= '</tbody></table>';

		return $result;
	}


}
