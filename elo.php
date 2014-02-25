<?php
/*
Plugin Name: Elo Calculator
Plugin URI: http://matthewgruman.com/elo
Description: A plugin to calculate Elo rankings for backgammon clubs
Version: 0.1.1b
Author: Matthew Gruman
Author URI: http://matthewgruman.com
License: GPL2
*/
add_action( 'admin_menu', 'my_plugin_menu' );

function admin_register_head() {
    $siteurl = get_option('siteurl');
    $url = $siteurl . '/wp-content/plugins/elo/timepicker/jquery-ui-timepicker-addon.css';
    echo "<link rel='stylesheet' type='text/css' href='$url' />\n";

    echo '<link rel="stylesheet" media="all" type="text/css" href="http://code.jquery.com/ui/1.10.3/themes/smoothness/jquery-ui.css" />';

    echo '<script type="text/javascript" src="http://code.jquery.com/ui/1.10.3/jquery-ui.min.js"></script>';

    wp_enqueue_script('jquery-ui-timepicker-addon',$siteurl . '/wp-content/plugins/elo/timepicker/jquery-ui-timepicker-addon.js');
    wp_enqueue_script('jquery-ui-sliderAccess',$siteurl . '/wp-content/plugins/elo/timepicker/jquery-ui-sliderAccess.js.js');
	#echo '<script type="text/javascript" src="'.$siteurl . '/wp-content/plugins/elo/timepicker/jquery-ui-timepicker-addon.js"></script>';
	#echo '<script type="text/javascript" src="'.$siteurl . '/wp-content/plugins/elo/timepicker/jquery-ui-sliderAccess.js"></script>';
}
#add_action('admin_head', 'admin_register_head');


add_action('in_admin_footer', 'admin_register_head');

function my_plugin_menu() {
	add_menu_page( 'Elo Calculator', 'Elo Calculator', 'manage_options', 'elo', 'my_plugin_options');
}

function recalculate_rating(){
    global $wpdb;
    $table = $wpdb->prefix.elo;
    $tabled = $wpdb->prefix.elomatches;

    $wpdb->query("UPDATE $table SET elo=1000,experience=0");

    $matches = $wpdb->get_results("
      SELECT m.*,t1.fn as winner,t2.fn as loser
      FROM $tabled as m
      INNER JOIN $table as t1 ON t1.id=m.winnerid
      INNER JOIN $table as t2 ON t2.id=m.loserid
      ORDER BY `date` ASC");

    foreach ($matches as $m)
    {
        $p1 = $wpdb->get_row("SELECT * FROM $table WHERE id = ".$m->winnerid);
        $p2 = $wpdb->get_row("SELECT * FROM $table WHERE id = ".$m->loserid);

        $p1elo = $p1->elo;
        $p2elo = $p2->elo;

        $p1exp = $p1->experience+1;
        $p2exp = $p2->experience+1;

        $win = $m->winnerid;
        $u1 =  1/((pow(10, ($p2elo-$p1elo)/400))+1);
        $p1 = $p1elo+30*(1-$u1);
        // elo for undo
        $winelo = $p1elo;

        $lose = $m->loserid;
        $u2 = 1/((pow(10, ($p1elo-$p2elo)/400))+1);
        $p2 = $p2elo+30*(0-$u2);
        // elo for undo
        $losselo = $p2elo;

        $points = 30*(1-$u1);

        $wpdb->update($table, array('elo' => $p1, 'experience' => $p1exp), array('id' =>$m->winnerid));
        $wpdb->update($table, array('elo' => $p2, 'experience' => $p2exp), array('id' =>$m->loserid));

        $wpdb->update($tabled, array('winnerelo' => $winelo, 'loserelo' => $losselo, 'points' => round($points)),array('matchid' => $m->matchid));
    }
}

function my_plugin_options() {
	if ( !current_user_can( 'manage_options' ) )  {
		wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
	}

	global $wpdb;
	$table = $wpdb->prefix.elo;
	$tabled = $wpdb->prefix.elomatches;

	// check if elo db exists, otherwise create it
	if($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table)
	{
		$sql = "CREATE TABLE IF NOT EXISTS $table (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `fn` varchar(255) NOT NULL,
          `elo` int(4) NOT NULL DEFAULT '1000',
          `experience` int(11) NOT NULL,
          UNIQUE KEY `id` (`id`)
        );";

		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql);
	}

	// check if elomatches db exists, otherwise create it
	if($wpdb->get_var("SHOW TABLES LIKE '$tabled'") != $tabled)
	{
		$sql = "CREATE TABLE IF NOT EXISTS $tabled (
          `matchid` int(11) NOT NULL AUTO_INCREMENT,
          `winnerid` int(11) NOT NULL,
          `loserid` int(11) NOT NULL,
          `date` int(11) NOT NULL,
          `matchlength` int(11) NOT NULL,
          `winnerelo` int(11) NOT NULL,
          `loserelo` int(11) NOT NULL,
          `points` int(11) NOT NULL,
          PRIMARY KEY (`matchid`)
        );";

		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql);
	}
/*
    if (isset($_REQUEST['import']) and $_REQUEST['import'])
    {
        $content = file_get_contents(ABSPATH . 'wp-content/plugins/elo/log.csv');
        $content = explode("\n",$content);
        $i = 55;
        foreach ($content as $c)
        {
            $row = explode(",",$c);
            if ($row[0] and $row[1])
            {
                #var_dump($row);
                $user1 = $wpdb->get_var("SELECT id FROM $table WHERE fn='".str_replace("\r","",trim($row[0]))."';");
                #var_dump($user1);
                if (!$user1)
                {
                    $wpdb->insert(
                        $table,
                        array(
                            'fn' => str_replace("\r","",trim($row[0])),
                            'elo' => 1000
                        )
                    );
                    $user1 = $wpdb->insert_id;
                }
                $user2 = $wpdb->get_var("SELECT id FROM $table WHERE fn='".str_replace("\r","",trim($row[1]))."';");
                if (!$user2)
                {
                    $wpdb->insert(
                        $table,
                        array(
                            'fn' => str_replace("\r","",trim($row[1])),
                            'elo' => 1000
                        )
                    );
                    $user2 = $wpdb->insert_id;
                }

                $win = $user2;
                $lose = $user1;
                $wpdb->insert($tabled, array('winnerid' => $win, 'loserid' => $lose, 'date' => time()-($i*86400)));
                $i--;
            }
        }
        recalculate_rating();
    }
*/
    //delete match
    if (isset($_REQUEST['deletematch']) and $_REQUEST['deletematch'])
    {
        $id = $_REQUEST['deletematch'];
        $wpdb->query("DELETE FROM $tabled WHERE matchid='$id'");
        recalculate_rating();
    }

	// delete users
	if (isset($_GET['delete']) && is_numeric($_GET['delete']))
	{
		$id = $_GET['delete']; 
		// delete from elo
		$wpdb->query("DELETE FROM $table WHERE id='$id'");
		// delete from elomatch
		$wpdb->query("DELETE FROM $tabled WHERE winnerid='$id' || loserid = '$id'");
	}

	// undo last match
	if (isset($_GET['undo']) && is_numeric($_GET['undo']))
	{
		// get last match info
		$matchid = $_GET['undo'];
		$last = $wpdb->get_row("SELECT * FROM $tabled WHERE matchid='$matchid';");
		$match = $last->matchlength;

		$winnerid = $last->winnerid;
		$winnerelo = $last->winnerelo;
		$exp = $wpdb->get_row("SELECT * FROM $table WHERE id='$winnerid';");
		$winnerexp = $exp->experience - 1;

		$loserid = $last->loserid;
		$loserelo = $last->loserelo;
		$exp = $wpdb->get_row("SELECT * FROM $table WHERE id='$loserid';");
		$loserexp = $exp->experience - 1;

		// input old elo and experience to the winner
		$wpdb->update($table, array('elo' => $winnerelo, 'experience' => $winnerexp), array('id' =>$winnerid));
		// input old elo to the loser
		$wpdb->update($table, array('elo' => $loserelo, 'experience' => $loserexp), array('id' =>$loserid));
		
		// delete match
		$wpdb->query("DELETE FROM $tabled WHERE matchid='$matchid'");
		
		?><p class="success">&#10004; Бой удален</p><?php
	}

	// submit forms
	
	if (isset($_POST['add_submit']))
	{
	    // insert new player with default Elo of 1000
		$errors = array();
		if (isset($_POST['fn']) && $_POST['fn'] != 'First name')
		{
			$fn = $wpdb->escape($_POST['fn']);
		}
		else
		{
			$errors[] = 'Пожалуйста введите имя';
		}

		if (empty($errors))
		{ // enter new player
			$elo = '1000';
			$wpdb->insert(
				$table,
				array(
					'fn' => $fn,
					'elo' => $elo
				)
			);
			?>
        <p class="success">&#10004; Игрок добавлен</p><?php
			unset($fn);
		}
		else
		{
			?><h2 class="delete">Ошибки</h2>
			<ul><?php
				foreach ($errors as $error)
				{
					?><li><?=$error;?></li><?php
				}
			?></ul><?php
		}
	}
	
	if (isset($_REQUEST['submit']))
	{ // calculate Elo
        var_dump($_REQUEST);
		$errors = array();
		// IDs

        if (isset($_REQUEST['winner_name']) and $_REQUEST['winner_name'])
        {
            #var_dump($wpdb->escape($_REQUEST['winner_name']));
            $winner = $wpdb->get_row("SELECT * FROM $table WHERE fn = '".$wpdb->escape($_REQUEST['winner_name'])."'");
            #var_dump($winner);
            if (!$winner->id)
            {
                $wpdb->insert(
                    $table,
                    array(
                        'fn' => $wpdb->escape($_REQUEST['winner_name']),
                        'elo' => 1000
                    )
                );
                $_POST['playerone'] = $wpdb->insert_id;
            }
            else
                $_POST['playerone'] = $winner->id;
        }

        if (isset($_REQUEST['loser_name']) and $_REQUEST['loser_name'])
        {
            $loser = $wpdb->get_row("SELECT * FROM $table WHERE fn = '".$wpdb->escape($_REQUEST['loser_name'])."'");
            if (!$loser->id)
            {
                $wpdb->insert(
                    $table,
                    array(
                        'fn' => $wpdb->escape($_REQUEST['loser_name']),
                        'elo' => 1000
                    )
                );
                $_POST['playertwo'] = $wpdb->insert_id;
            }
            else
                $_POST['playertwo'] = $loser->id;
        }

        if (isset($_REQUEST['date']) and $_REQUEST['date'])
        {
            $date = explode(" ",$_REQUEST['date']);
            $day = explode("-",$date[0]);
            $time = explode(":",$date[1]);
            $date = mktime($time[0],$time[1],0,$day[1],$day[0],$day[2]);
        }

		if (isset($_POST['playerone']) && is_numeric($_POST['playerone']))
		{
			$p1id = $wpdb->escape($_POST['playerone']);
		}
		else
		{
			$errors[] = 'Please select Player one';
		}
		if (isset($_POST['playertwo']) && is_numeric($_POST['playertwo']))
		{
			$p2id = $wpdb->escape($_POST['playertwo']);
		}
		else
		{
			$errors[] = 'Please select Player two';
		}
		
		// are they same?
		
		if ($p1id == $p2id)
		{
			$errors[] = 'Players cannot be the same.';
		}

        $n = 0;
		if (empty($errors))
		{
            /*
		    // do all calculations
			$p1 = $wpdb->get_row("SELECT * FROM $table WHERE id = $p1id");
			$p2 = $wpdb->get_row("SELECT * FROM $table WHERE id = $p2id");

			$p1elo = $p1->elo;
			$p2elo = $p2->elo;

			$p1exp = $p1->experience+1;
			$p2exp = $p2->experience+1;

            $win = $p1id;
            $u1 =  1/((pow(10, ($p2elo-$p1elo)/400))+1);
            $p1 = $p1elo+30*(1-$u1);
            // elo for undo
            $winelo = $p1elo;

            $lose = $p2id;
            $u2 = 1/((pow(10, ($p1elo-$p2elo)/400))+1);
            $p2 = $p2elo+30*(0-$u2);
            // elo for undo
            $losselo = $p2elo;

            $points = 30*(1-$u1);


			$wpdb->update($table, array('elo' => $p1, 'experience' => $p1exp), array('id' =>$p1id));
			$wpdb->update($table, array('elo' => $p2, 'experience' => $p2exp), array('id' =>$p2id));

			$wpdb->insert($tabled, array('winnerid' => $win, 'loserid' => $lose, 'date' => $date,'winnerelo' => $winelo, 'loserelo' => $losselo, 'points' => round($points)));
			*/

            $win = $p1id;
            $lose = $p2id;
            $wpdb->insert($tabled, array('winnerid' => $win, 'loserid' => $lose, 'date' => $date));
            recalculate_rating();
			?>
            <p class="success">&#10004; Рейтинг обновлен</p><?php
			unset($win);
			unset($lose);
			unset($winner);
			unset($n);
			unset($p1id);
			unset($p2id);
		}
		else
		{
			?><h2 class="delete">Ошибки</h2>
			<ul><?php
				foreach ($errors as $error)
				{
					?><li><?=$error;?></li><?php
				}
			?></ul>
<?php
		}
	}
	// last match date
	$lastmatch = $wpdb->get_row("SELECT MAX(date) AS updated FROM $tabled ORDER BY date DESC;");
    
	// get last match
	$lastwin = $wpdb->get_row("SELECT * FROM $tabled INNER JOIN $table ON $tabled.winnerid=$table.id ORDER BY $tabled.date DESC LIMIT 1;");
	$lastloss = $wpdb->get_row("SELECT * FROM $tabled INNER JOIN $table ON $tabled.loserid=$table.id ORDER BY $tabled.date DESC LIMIT 1;");
	// get user list
	$users = $wpdb->get_results("SELECT * FROM $table ORDER BY fn ASC;");
	$elo = $wpdb->get_results("SELECT * FROM $table ORDER BY elo DESC, fn ASC;");
	?><div class="wrap">
    <script>
        jQuery(document).ready(function(){
            jQuery('#date_input').datetimepicker({
                dateFormat: "dd-mm-yy"
            });
        })
    </script>
		<style>
			ul.half li {width: 25%; float: left; font-family: georgia;}
			.wrap p {font-family: georgia; font-size: 1.2em;}
			.length {font-size: 1.2em; margin-right: 20px; margin-left: 5px;}
			.select {font-size: 1.2em;}
			table {font-size: 1.4em; line-height: 1.4em;}
			input[type=submit] {font-size: 1.4em; padding: 5px;}
			.delete {color: red; font-size: 2em; font-weight: bold;}
			.success:first-letter {color: green; font-size: 2em; font-weight: bold;}
			.small-caps {font-variant: small-caps;}
		</style>
		<h2><a href="admin.php?page=elo">Elo Рейтинг</a></h2>
		<p><strong>Последний бой:</strong> <?=$lastwin->fn;?> <span class="small-caps">vs</span> <?=$lastloss->fn;?> on <?=date('F j, Y @ h:ia', $lastmatch->updated);?></p>
		<form name="new_elo" method="post" action="<?=$_SERVER['REQUEST_URI'];?>">
		<p>
			<label for="new_player">Добавить игрока:</label>
			<input type="text" placeholder="Ник" name="fn" id="new_player"/>
			<input type="submit" value="Добавить" name="add_submit" />
		</p>
		<hr />
        <h3>Добавить бой</h3>
		<form name="add_elo" method="post" action="<?=$_SERVER['REQUEST_URI'];?>">
			<ul class="half">
                <li>
                    <h3>Время</h3>
                    <input type="text" name="date" id="date_input">
                </li>
				<li>
					<h3>Победитель</h3>
					<select name="playerone" class="select">
						<option></option>
						<?php 
						foreach ($users as $row)
						{
							if (isset($p1id) && $p1id == $row->id)
							{
								$selected = ' SELECTED';
							}
							else
							{
								$selected = NULL;
							}
							?><option value="<?=$row->id;?>"<?=$selected;?>><?=$row->fn;?> <?=$row->ln;?> (<?=$row->elo;?>)</option><?php
						}
						?>
					</select>
				</li>
				<li>
					<h3>Проигравший</h3>
					<select name="playertwo" class="select">
						<option></option>
						<?php 
						foreach ($users as $row)
						{
							if (isset($p2id) && $p2id == $row->id)
							{
								$selected = ' SELECTED';
							}
							else
							{
								$selected = NULL;
							}
							?><option value="<?=$row->id;?>"<?=$selected;?>><?=$row->fn;?> <?=$row->ln;?> (<?=$row->elo;?>)</option><?php
						}
						?>
					</select>
				</li>
                <li>
                    <h3>&nbsp;</h3>
                    <input type="submit" value="Добавить" name="submit" />
                </li>
			</ul>
			<br style="clear: both" />
		</form>
		<br />
		<table class="widefat">
		<thead>
			<tr>
				<th>Игрок</th>
				<th>Elo-рейтинг</th>
				<th>Статистика</th>
				<th>Винрейт</th>
				<th>Боев</th>
				<!--th>Удалить</th-->
			</tr>
		</thead>
		<tbody>
    <?php

			foreach ($elo as $row)
			{
				$wins = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $tabled WHERE winnerid = $row->id;",array()));
				$losses = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $tabled WHERE loserid = $row->id;",array()));
				?><tr>
					<td><?=$row->fn;?> <?=$row->ln;?></td>
					<td><?=$row->elo;?></td>
					<td><?=$wins;?> - <?=$losses;?></td>
					<td><?php if ($wins > 0) {echo 100*number_format($wins/($wins+$losses), 2).'%';} else {echo '0%';}?></td>
					<td><?=$row->experience;?></td>
					<!--td><a class="delete" href="<?php echo site_url(); ?>/wp-admin/admin.php?page=elo&amp;delete=<?=$row->id;?>">&times;</a></td-->
				</tr><?php
			}
			?>
		</tbody>
		</table>
        <br />
        <!--p><a href="admin.php?page=elo&amp;undo=<?=$lastwin->matchid;?>">Удалить последний матч</a></p-->
        <h2>Бои</h2>
<?php
    $matches = $wpdb->get_results("
      SELECT m.*,t1.fn as winner,t2.fn as loser
      FROM $tabled as m
      INNER JOIN $table as t1 ON t1.id=m.winnerid
      INNER JOIN $table as t2 ON t2.id=m.loserid
      ORDER BY `date` DESC");
?>

        <table class="widefat">
        <thead>
            <tr>
                <th>Дата</th>
                <th>Победитель</th>
                <th>Проигравший</th>
                <th>Очки</th>
                <th>Удалить</th>
            </tr>
        </thead>
        <tbody>
            <?php
            foreach ($matches as $row)
            {
            ?>
                <tr>
                    <td><?=date("d-m-Y H:i",$row->date);?></td>
                    <td><?=$row->winner.' ('.$row->winnerelo.')';?></td>
                    <td><?=$row->loser.' ('.$row->loserelo.')';?></td>
                    <td><?=$row->points;?></td>
                    <td><a class="delete" href="<?php echo site_url(); ?>/wp-admin/admin.php?page=elo&amp;deletematch=<?=$row->matchid;?>">&times;</a></td>
                </tr>
            <?php
            }
            ?>
        </tbody>
        </table>

	</div><?php
}



function show_elo_table()
{
    global $wpdb;
    $table = $wpdb->prefix.elo;
    $tabled = $wpdb->prefix.elomatches;
    $elo = $wpdb->get_results("SELECT * FROM $table ORDER BY elo DESC, fn ASC;");
    $table = '<table class="widefat">
		<thead>
			<tr>
				<th>Участник</th>
				<th>Кол-во боев</th>
				<th>ELO-рейтинг</th>
				<th>Статистика</th>
				<th>Винрейт</th>
			</tr>
		</thead>
		<tbody>';

			foreach ($elo as $row)
			{
				$wins = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $tabled WHERE winnerid = $row->id;",array()));
				$losses = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $tabled WHERE loserid = $row->id;",array()));
                $winrate = ($wins > 0)?(100*number_format($wins/($wins+$losses), 2).'%'):'0%';
				$table .='<tr>
					<td>'.$row->fn.'</td>
					<td>'.$row->experience.'</td>
					<td>'.$row->elo.'</td>
					<td>'.$wins.' - '.$losses.'</td>
					<td>'.$winrate.'</td>
				</tr>';
			}
$table .='
		</tbody>
		</table>';
return $table;
}

add_shortcode('elotable', 'show_elo_table');

function show_elo_matches()
{
    global $wpdb;
    $table = $wpdb->prefix.elo;
    $tabled = $wpdb->prefix.elomatches;

    $matches = $wpdb->get_results("
      SELECT m.*,t1.fn as winner,t2.fn as loser
      FROM $tabled as m
      INNER JOIN $table as t1 ON t1.id=m.winnerid
      INNER JOIN $table as t2 ON t2.id=m.loserid
      ORDER BY `date` DESC");

$result = '
        <table class="widefat">
        <thead>
            <tr>
                <th>Дата</th>
                <th>Победитель</th>
                <th>Проигравший</th>
                <th>Очки</th>
            </tr>
        </thead>
        <tbody>';
            foreach ($matches as $row)
            {
            $result .= '
                <tr>
                    <td>'.date("d-m-Y H:i",$row->date).'</td>
                    <td>'.$row->winner.' ('.$row->winnerelo.')</td>
                    <td>'.$row->loser.' ('.$row->loserelo.')</td>
                    <td>'.$row->points.'</td>
                </tr>';
            }

$result .='
        </tbody>
        </table>';
    return $result;
}

add_shortcode('elomatches', 'show_elo_matches');