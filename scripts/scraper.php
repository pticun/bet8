<?php
require_once('simple_html_dom.php');
require_once(__DIR__.'/../vendor/autoload.php');
require_once(__DIR__.'/../generated-conf/config.php');


class ScheduleScraper {
  
  /* class variables to use in scraper */
  private $hockey_url = 'http://www.hockey-reference.com/leagues/NHL_2017_games.html';
  private $basketball_url = '';
  private $football_url = '';

  private $hockey_map = array(
			      'date' => array('col' => 0),
			      'option1' => array('col' => 1),
			      'option1Score' => array('col' => 2),
			      'option2' => array('col' => 3),
			      'option2Score' => array('col' => 4)
			      );

  function getPageHTML($url){
    $ch = curl_init();
    $user_agent='Mozilla/5.0 (Windows NT 6.1; rv:8.0) Gecko/20100101 Firefox/8.0';
    curl_setopt ($ch, CURLOPT_URL, $url);
    curl_setopt ($ch, CURLOPT_CUSTOMREQUEST, "GET");
    curl_setopt ($ch, CURLOPT_USERAGENT, $user_agent);
    curl_setopt ($ch, CURLOPT_HEADER, 0);
    curl_setopt ($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt ($ch,CURLOPT_CONNECTTIMEOUT,120);
    curl_setopt ($ch,CURLOPT_TIMEOUT,120);
    curl_setopt ($ch,CURLOPT_MAXREDIRS,10);
    return curl_exec ($ch);
  }
  /* Function to scrape scheduled events/outcomes from hockey-reference.com */
  function ScrapeHockey() {
    $html = new simple_html_dom();
    $html->load(
		$this->getPageHTML($this->hockey_url)
		);
    $games = $html->find('table[id=games]')[0]->find('tr');
    $_ = array_shift($games);
    $values = array();
    foreach($games as $game) {
      $row = array_merge($game->find('th'), $game->find('td'));
      $value_row = array(); 
      foreach($row as $col){
	$value = ($col->find('a') ? $col->find('a')[0] : $col);
        $value = $value->plaintext;
        array_push($value_row, $value);
      }
      array_push($values, $value_row);
    }
    /* Debugging output to get layout of scraped data
    $debug = fopen('scraper.txt', 'w');
    ob_start();
    var_dump($values);
    fwrite($debug, ob_get_clean());
    fclose($debug);*/
    return $values;
  }
  
  /* Function insert and update new events/outcomes from the scrapped data using Propel classes */
  function UpdateData($values, $sport_name){
    $sport = SportsQuery::create()
      ->filterBySport($sport_name)
      ->findOne();

    // Create new sport if it does not exist
    if (count($sport) < 1) {
      $sport = new Sports();
      $sport->setSport($sport_name);
      $sport->save();
    }
      
    foreach($values as $row){
      $event = EventsQuery::create()
	->filterByDate($row[$this->hockey_map['date']['col']])
	->useOptionsQuery() // sub query to filter events with the same options text
	  ->filterByText($row[$this->hockey_map['option1']['col']],
			 $row[$this->hockey_map['option2']['col']])
	->endUse()
	->with('Options') //Use a join to hit the database only once (i.e. prefect related with join)
	->find(); // execute query
      
      /* TODO remove this and put it in the proper query context */
      /* query to get events in the next 'x' days for a $sport */
      /*$events = EventQuery::create()
	->filterByDate(array('min' => *today*, 'max' => *today+x-days*)
	->filterBySport($sport)
	->with('Options')
	->find();*/
      
      if (count($event) < 1) {
	$event = new Events();
	$event.setTitle($this->getEventTitle($row));
	$event.setDate($row[$this->hockey_map['date']['col']]);
	$event.setSport($sport);
	
	$option1 = new Options();
	$option1.setText($row[$this->hockey_map['option1']['col']]);
	$option1.setVoteCount(0);
	$option1.setImage("");

	$option2 = new Option();
	$option2.setText($row[$this->hockey_map['option2']['col']]);
	$option2.setVoteCount(0);
	$option2.setImage("");
	$event.addOption($option1);
	$event.addOption($option2);
	$event.save();
	
      }
      if ($event->getOptions()[0] == null) {
	$options = $this->setWinningOption(
					   $row[$this->hockey_map['option1']['col']],
					   $row[$this->hockey_map['option2']['col']],
					   $event->getOptions()[0],
					   $event->getOptions()[1]
					   );
      }
    }
  }
  /* Function to get the name of an event for two team */
  function getHockeyEventTitle($row){
    return $row[$this->hockey_map['option1']['col']]." @ ".$row[$this->hockey_map['option2']['col']];
  }
  
  /* Function calculate the winning option based on the scores given */
  function setWinningOption($option1Val, $option2Val, $option1, $option2) {
    if($row[$this->hockey_map['option1']['col']] != '' && $row[$this->hockey_map['option2']['col']]) {
      if ((int)$row[$this->hockey_map['option1']['col']] > (int)$row[$this->hockey_map['option2']['col']]) {
	    $option1->correct = true;
	    $option2->correct = false;
      } else {
	    $option1->correct = false;
	    $option2->correct = true;
      }
    } else {
	  $option1->correct = null;
	  $option2->correct = null;
	  
    }
    $option1->save();
    $option2->save();
  }

/*
class CalculatePredictionStatistics {
  
}*/
}

/* Define main script in this function container */
function MainUpdate() {
  $scraper = new ScheduleScraper();
  $hockey_values = $scraper->ScrapeHockey();
  $scraper->UpdateData($hockey_values, 'hockey');
}

/* Run the main script */
MainUpdate();
 
