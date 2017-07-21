<!-- List one quiz's details  -->

    <H3>Quiz Details</H3>
<div class="panel panel-default">
	 	 
<?php
require ("user-session.php");
require ("utility.php");

$lid = intval($_REQUEST['lid']);
$sql = "SELECT * FROM `info` WHERE uid = '$uid' and lid = $lid";
if ($result = $mysqli->query($sql))
{
	if ($row = $result->fetch_assoc())
	{
		$data = json_decode($row['data'], TRUE);
		$numPages = count($data['pages']); // .pages is array of pid's, order matches lesson order.
		?>
		<ul>
			<li>Title: <?=$data['title']?></li>
			<li>Description: <?=$data['calidescription']?></li>
			<li><?=$numPages?> Questions</li>
			<ol>
			<?php
			if ($numPages>0)
			{ 
				foreach ($data['pages'] as $pid)
				{
					$sql = "SELECT * FROM `page` WHERE pid = $pid";
					if ($result = $mysqli->query($sql))
					{
						if ($row = $result->fetch_assoc())
						{
							// Check page type so we get accurate detail (but as of 3/2017 there are all quiz type)
							$page = json_decode($row['data'] , TRUE);
							echo '<li>'.compactQuestionDescription($page);
							/*
							$pagetype = $page['page-type'];
							echo '<li>#'.$pid.': '.$page['page-question'];
							switch ($pagetype)
							{
								
								case 'quiz-yn':	// Yes/No 
									$isyes = $page['yes-is-correct']=='true';
									echo '(Y/N)';
									break;
								
								case 'quiz-tf':	// True/false 
									$istrue = $page['true-is-correct']=='true';
									echo '(T/F)';
									break;
								
								case 'Quiz':	// Multiple choice: 1 correct, 1-N wrong.
								case 'quiz-mc':
								case '':
									//echo '<ul>';
									//echo '<li class="correct">'.$page['page-choice-correct-text'];
									$count=1;
									for ($wrong=1;$wrong<=7;$wrong++)
									{
										$wrongText = $page['page-choice-wrong-'.$wrong.'-text'];
										if ($wrongText!='') echo $count ++; // '<li class="wrong">'.$wrongText;
									}
									//echo '</ul>';
									echo $count;
									break;
							}
							*/
						}
					}
				}
			}
			?>
			</ol>
		</ul>
		<?php 
	}
}
?>
<!-- Button -->

  <div class="col-sm-3 control-label" for="submit">Ready to give the quiz?</div>
  <div class="col-sm-8">
    <a id="quiz-publish"   class="btn btn-primary" target=_blank href="./includes/quiz-publish.php?lid=<?php echo $lid;?>">Publish Quiz</a>
    <li id="quiz-page-order"   class="btn btn-primary">Change Questions</li>
    <li id="quiz-info-edit"   class="btn btn-primary">Edit Description</li>
    <li id="quiz-cancel"   class="btn btn-primary">Publish Later</li>
  </div>
</div>



<script>
	$("#quiz-page-order").click(function(){$("#main-panel").load("./includes/quiz-page-order.php?lid=<?php echo $lid;?>");});
	$("#quiz-info-edit").click(function(){$("#main-panel").load("./includes/quiz-info-edit.php?lid=<?php echo $lid;?>");});
	$("#quiz-cancel").click(function(){$("#main-panel").load("./includes/quiz-list.php");});
</script>
