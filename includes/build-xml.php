<?php
/*
	SJG Library function to construct CALI Viewer compatible XML (the jqBookData.xml file).
	Currently returns multiple choices in random order so every call with same arguments could have different XML.
	Question are in autonumber order.
	
	05/10/2017
		Moved this function to this shared library since it's shared by AutoPublish, Previewer and XML Downloader (for use by other tools)
		Restored sample code since I find it handy reference to what XML looks like and efficiency is of little concern in infrequently called code like this.
	05/11/2017
		Added: Optional custom Introduction/Conclusion page.
		Added: Incorporate optional feedback.
	
	TODO: questions arranged in author specified order
*/
function makeQuestionTextXML($text)
{	// 05/11/2017 SJG Helper function
	return '<QUESTION ALIGN="AUTO">'.$text.'</QUESTION>';
}
function makePageXML($pageName,$pageType,$pageStyle,$nextPage,$innerXML)
{	// 05/11/2017 SJG Helper function to ensure markup is always right.
	return '<PAGE ID="'.$pageName.'" TYPE="'.$pageType.'" STYLE="'.$pageStyle.'" NEXTPAGE="'.$nextPage.'" NEXTPAGEDISABLED="False" SCORING="Totals" SORTNAME="'.$pageName.'">'.$innerXML.'</PAGE>';
}


function BuildXML($mysqli,$data,$author)
{	// 03/02/2017 SJG $data is lesson $data block with meta info and page list.
	$xml='';
	
	$description=
		'<DIV>'.$data['calidescription'].'</DIV>'
		.'<P>Approximate Completion Time: '. $data['completiontime']. '</P>'
		.'<BR /><BR />'
		.'<DIV style="font-size: .8em">'. $data['title']
		.'<BR />by '
		.'<BR />'.$author['authorfullname']
		.'<BR />'.$author['authortitle']
		.'<BR />'.$author['authorschool']
		.'</DIV>';
		
	$info=array(
		'TITLE'=>htmlspecialchars($data['title']),
		'SUBJECTAREA'=>htmlspecialchars($data['subjectarea']),
		'DESCRIPTION'=> ( $description),
		'COMPLETIONTIME'=>htmlspecialchars($data['completiontime']),
		'NOTES'=>'Book automatically created by QuizWright'
		
		);
	$xml.='<INFO>';
	foreach ($info as $key=>$value) $xml.='<'.$key.'>'. $value .'</'.$key.'>';
	$xml.='</INFO>';	
	// 05/11/2017 SJG Introduction and conclusion are optional, grab them and incorporate at the right spots.
	$pageIntroText = $data['quiz-intro'];
	$pageConclusionText = $data['quiz-conclusion'];
	$hasIntro = $pageIntroText != "";
	$hasConclusion = $pageConclusionText != "";
	
	$numPages = count($data['pages']);
	$firstPage='Question 1';

	// Build the Table of Contents
	$toc='<PAGE ID="Contents" TYPE="Topics" STYLE="0" NEXTPAGEDISABLED="True" SORTNAME="Contents"><TOC><UL>';
	if($hasIntro)
	{
		$toc .= '<LI><A HREF="Introduction">Introduction</A></LI>';
	}
	$toc .= '<LI><A HREF="Question 1">'.$numPages.' Questions</A></LI>';
	if($hasConclusion)
	{
		$toc .= '<LI><A HREF="Conclusion">Conclusion</A></LI>';
	}
	$toc .= '</UL></TOC> </PAGE>';
	$xml .= $toc;
	
	if ($hasIntro){
		$xml .= makePageXML('Introduction','Book Page','','Question 1',makeQuestionTextXML($pageIntroText));
	}
		
		

	
	$pageNum=0;
	if ($numPages>0)
	{ 
		foreach ($data['pages'] as $pid)
		{
			$pid=intval($pid);
			$sql = "SELECT data FROM `page` WHERE pid = $pid";
			if ($result = $mysqli->query($sql))
			{
				if ($row = $result->fetch_assoc())
				{
					// Check page type so we get accurate detail (but as of 3/2017 there are all quiz type) which translates to Multiple Choice type.
					$page = json_decode($row['data'], TRUE);
					$pagetype = $page['page-type'];
					$pageNum += 1;
					$pageName = 'Question '.$pageNum;
					$nextPage = ($pageNum < $numPages) ? ('Question '.($pageNum+1)) : ( $hasConclusion ? 'Conclusion' : 'Contents');
					$pageText = $page['page-question'];
					$pageFeedback =  $page['page-feedback'] ;
					$pageXML=''; 
					switch ($pagetype)
					{
						

// ### True/False style of quiz question
						case 'quiz-tf': // This will be a CA Buttons-only thype.
/* Sample True/False question XML from CALI Lesson
<PAGE ID="Erie Origins 3" TYPE="Multiple Choice" STYLE="Choose Buttons" NEXTPAGE="Erie Origins: SWIFT FALSE" NEXTPAGEDISABLED="True">
	<QUESTION ALIGN="AUTO">
		<P>
			<b>TRUE OR FALSE: Under</b>
			<b>
				<i>Swift v. Tyson</i>
			</b>
			<b>, state common law did not count as "law" that could apply in federal court through the Rules of Decision Act.</b>
		</P>
	</QUESTION>
	<BUTTON>TRUE</BUTTON>
	<BUTTON>FALSE</BUTTON>
	<FEEDBACK BUTTON="1" DETAIL="1" GRADE="RIGHT" NEXTPAGE="Erie Origins: SWIFT TRUE"></FEEDBACK>
	<FEEDBACK BUTTON="2" DETAIL="1" GRADE="WRONG" NEXTPAGE="Erie Origins: SWIFT FALSE"></FEEDBACK>
</PAGE>
*/							
							$istrue = $page['true-is-correct']=='true';
							$innerXML = makeQuestionTextXML($pageText)
								.'<BUTTON>True</BUTTON><BUTTON>False</BUTTON>'
								.'<FEEDBACK BUTTON="1" DETAIL="1" GRADE="'.(($istrue)?'RIGHT':'WRONG').'" NEXTPAGE="'.$nextPage.'"></FEEDBACK>'
								.'<FEEDBACK BUTTON="2" DETAIL="1" GRADE="'.((!$istrue)?'RIGHT':'WRONG').'" NEXTPAGE="'.$nextPage.'"></FEEDBACK>'
								.($pageFeedback!='' ? '<FEEDBACK>'.$pageFeedback.'</FEEDBACK>' : '');
							$pageXML=makePageXML($pageName, "Multiple Choice", "Choose Buttons",$nextPage, $innerXML);
							break;
						

// ### Standard quiz question which is multiple choice:
						case 'Quiz':
						case 'quiz-mc':
						case '':
/*	Sample multiple choice question XML from CALI Lesson
 *<PAGE ID="Question 20.2" TYPE="Multiple Choice" STYLE="Choose List" NEXTPAGE="Question 21" NEXTPAGEDISABLED="False" SCORING="Totals" SORTNAME="Question 20. 2">
	<QUESTION ALIGN="AUTO">
		<P>If parties do not agree on a place of delivery, the place of delivery is:</P>
	</QUESTION>
	<DETAIL>
		<P>seller's place of business (or residence if none).</P>
	</DETAIL>
	<DETAIL>
		<P>buyer's place of business (or residence if none).</P>
	</DETAIL>
	<DETAIL>
		<P>a point halfway between seller's and buyer's place of business.</P>
	</DETAIL>
	<DETAIL>
		<P>Hoboken, New Jersey.</P>
	</DETAIL>
	<FEEDBACK BUTTON="1" DETAIL="1" GRADE="RIGHT">
		<P>Right! The authority for this answer is UCC &#167;&#160;2-308.</P>
	</FEEDBACK>
	<FEEDBACK BUTTON="1" DETAIL="2" GRADE="WRONG">
		<P>Sorry.</P>
	</FEEDBACK>
	<FEEDBACK BUTTON="1" DETAIL="3" GRADE="WRONG">
		<P>Sorry.</P>
	</FEEDBACK>
	<FEEDBACK BUTTON="1" DETAIL="4" GRADE="WRONG">
		<P>I'm glad you have a sense of humor, but I hope you get it right on the next try.</P>
	</FEEDBACK>
</PAGE>
*/							$choices=array(); // assemble feedbacks, which we will shuffle
							$choices[] = array("DETAIL"=>$page['page-choice-correct-text'],"GRADE"=>"RIGHT");
							for ($wrong=1;$wrong<=7;$wrong++)
							{
								$wrongText = $page['page-choice-wrong-'.$wrong.'-text'];
								if ($wrongText!=''){							
									$choices[] = array("DETAIL"=>$wrongText,"GRADE"=>"WRONG");
									}
							} 
							shuffle($choices);							
							$choicei=0;
							$details='';
							$feedbacks='';
							foreach($choices as $choice)
							{
								$choicei++;
								$details .= '<DETAIL>'.$choice['DETAIL'].'</DETAIL>';
								$feedbacks.='<FEEDBACK BUTTON="1" DETAIL="'.$choicei.'" GRADE="'.$choice['GRADE'].'" NEXTPAGE="'.$nextPage.'"></FEEDBACK>';
							}
							$innerXML = makeQuestionTextXML($pageText)
								. $details.$feedbacks
								.( ($pageFeedback != "") ? ('<FEEDBACK>'.$pageFeedback.'</FEEDBACK>') : '');
							$pageXML=makePageXML($pageName,"Multiple Choice","Choose List",$nextPage,$innerXML);
							break;
						
						
						default:
							// Should not get here.
					}
					$xml .= $pageXML;
				}
			}
		}				
	}
	if ($hasConclusion){
		$xml .= makePageXML('Conclusion','Book Page','','Contents',makeQuestionTextXML($pageConclusionText));
	}

	$xml = '<?xml version="1.0" ?><BOOK>'.$xml.'</BOOK>';
	return $xml;
}
/*
 <?xml version="1.0" ?>
<BOOK>
<INFO><TITLE>A Copyright Quiz</TITLE>
<LESSON>A COPYRIGHT QUIZ</LESSON>
<SUBJECTAREA>Copyright</SUBJECTAREA>
<EMAILCONTACT></EMAILCONTACT>
<VERSION>04/11/2015</VERSION>
<CAVERSIONREQUIRED>4.2.1</CAVERSIONREQUIRED>
<DISTRIBUTION>Personal</DISTRIBUTION>
<COMPLETIONTIME>10 minutes</COMPLETIONTIME>
<CREATEDATE>2015-04-11 08:19:43</CREATEDATE>
<MODIFYDATE>2015-04-11 08:57:56</MODIFYDATE>
<COPYRIGHTS>Copyright 2015</COPYRIGHTS>
<CREDITS></CREDITS>
<CALIDESCRIPTION><P>How well do you know basic copyright law? This will show you.</P></CALIDESCRIPTION>
<AUTHORS><AUTHOR><NAME>Elmer R Masters</NAME>
<TITLE>Director of Technology</TITLE>
<SCHOOL>CALI</SCHOOL>
<EMAIL>emasters@cali.org</EMAIL>
<PHONE>773-332-7508</PHONE>
<WEBADDRESS>http://www.cali.org</WEBADDRESS>
</AUTHOR>
</AUTHORS>
<NOTES>04/11/2015 08:19:43: Book was created</NOTES>
<DESCRIPTION><P>How well do you know basic copyright law? This will show you.</P>
<P>Approximate Completion Time: 10 minutes</P>
<BR /><BR /><BR />
<div style='font-size: .8em'>A Copyright Quiz<BR />by
<P>Elmer R Masters<BR />Director of Technology<BR />CALI<BR /><A HREF="mailto:emasters@cali.org">emasters@cali.org</A> <A HREF="http://www.cali.org">http://www.cali.org</A> </P>
<P>Copyright 2015<BR />CALI Author Copyright 1999-2012 Center for Computer-Assisted Legal Instruction.</P>
<P>Version 04/11/2015</P>
</div>
</DESCRIPTION>
<CBKLOCATION>C:\CCALI\my books\A Copyright Quiz.CBK</CBKLOCATION>
</INFO>

<PAGE ID="Contents" TYPE="Topics" STYLE="0" NEXTPAGEDISABLED="True" SORTNAME="Contents"><TOC><UL><LI><A HREF="Introduction">Introduction</A></LI><LI><A HREF="Question 1">Questions</A></LI><LI><A HREF="Conclusion">Conclusion</A></LI></UL></TOC> </PAGE> 
<PAGE ID="Introduction" TYPE="Book Page" NEXTPAGE="Contents" NEXTPAGEDISABLED="False" SORTNAME="Introduction"><TEXT ALIGN="AUTO"><P>This short quiz on the basics of copyright will test your understanding of general topics in copyright law.</P></TEXT> </PAGE> 
<PAGE ID="Question 1" TYPE="Multiple Choice" STYLE="Choose Buttons" NEXTPAGE="Question 2" NEXTPAGEDISABLED="False" SCORING="Totals" SORTNAME="Question    1"><QUESTION ALIGN="AUTO"><P>You must register your copyright otherwise published materials are copyright free.</P></QUESTION> <BUTTON>True</BUTTON> <BUTTON>False</BUTTON> <FEEDBACK BUTTON="1" DETAIL="1" GRADE="WRONG"><P><B>No</B>. The default position is that all works are copyrighted as &quot;all rights reserved&quot;. You should generally assume that all works are protected by copyright. </P></FEEDBACK> <FEEDBACK BUTTON="2" DETAIL="1" GRADE="RIGHT"><P><B>Correct</B>. There is no requirement to &quot;register&quot; copyright. The default position is &quot;all rights reserved&quot; copyright. You should generally assume that all works are protected by copyright.</P></FEEDBACK> </PAGE> 
<PAGE ID="Conclusion" TYPE="Book Page" STYLE="Choose Buttons" NEXTPAGE="Contents" NEXTPAGEDISABLED="False" SORTNAME="Conclusion"><TEXT ALIGN="AUTO"><P><SMALL>Acknowledgement: Some questions in this quiz were inspired by &quot;<A HREF="[http://www.smartcopying.edu.au/scw/go/pid/657 ">Some common misconceptions</A>&quot; by <A HREF="[http://www.smartcopying.edu.au/scw/go/pid/1">smartcopying</A>.</SMALL></P></TEXT> </PAGE> 
<PAGE ID="Question 2" TYPE="Multiple Choice" STYLE="Choose Buttons" NEXTPAGE="Question 3" NEXTPAGEDISABLED="False" SCORING="Totals" SORTNAME="Question    2"><QUESTION ALIGN="AUTO"><P>You are free to repackage and sell content sourced from <A HREF="[http://en.wikipedia.org/wiki/English_Wikipedia">Wikipedia</A>, the free encyclopedia.</P></QUESTION> <BUTTON>True</BUTTON> <BUTTON>False</BUTTON> <FEEDBACK BUTTON="1" DETAIL="1" GRADE="RIGHT"><P><B>Correct</B>. There are no commercial restrictions on repackaging Wikipedia content, as long as your products are licensed under the same license used by Wikipedia.</P></FEEDBACK> <FEEDBACK BUTTON="2" DETAIL="1" GRADE="WRONG"><P><B>Incorrect</B>. The copyright license used by Wikipedia does not restrict commercial activity on condition that you share your products under the same license. </P></FEEDBACK> </PAGE> 
<PAGE ID="Question 3" TYPE="Multiple Choice" STYLE="Choose Buttons" NEXTPAGE="Question 4" NEXTPAGEDISABLED="False" SCORING="Totals" SORTNAME="Question    3"><QUESTION ALIGN="AUTO"><P>You are free to copy and reuse content which can be openly accessed on the web for educational purposes.</P></QUESTION> <BUTTON>True</BUTTON> <BUTTON>False</BUTTON> <FEEDBACK BUTTON="1" DETAIL="1" GRADE="WRONG"><P><B>No</B>. The fact that a resource is accessible on a public website does not necessarily change the copyright protections.</P></FEEDBACK> <FEEDBACK BUTTON="2" DETAIL="1" GRADE="RIGHT"><P><B>Correct</B>. Public access on a website does not necessarily grant permissions for reusing and copying materials for educational purposes.</P></FEEDBACK> </PAGE> 
<PAGE ID="Question 4" TYPE="Multiple Choice" STYLE="Choose Buttons" NEXTPAGE="Question 5" NEXTPAGEDISABLED="False" SCORING="Totals" SORTNAME="Question    4"><QUESTION ALIGN="AUTO"><P>You can reuse, adapt and modify content published in the Public Domain without attributing the source.</P></QUESTION> <BUTTON>True</BUTTON> <BUTTON>False</BUTTON> <FEEDBACK BUTTON="1" DETAIL="1" GRADE="RIGHT"><P><B>Correct</B>. The public domain means that the holder has waived all copyrights including the requirement for attribution. However, from an ethical perspective, attributing your sources is the right thing to do.</P></FEEDBACK> <FEEDBACK BUTTON="2" DETAIL="1" GRADE="WRONG"><P>Incorrect. While attributing your sources is the right thing to do -- there is no legal requirement to attribute the source of works published in the public domain.</P></FEEDBACK> </PAGE> 
<PAGE ID="Question 5" TYPE="Multiple Choice" STYLE="Choose Buttons" NEXTPAGE="Question 6" NEXTPAGEDISABLED="False" SCORING="Totals" SORTNAME="Question    5"><QUESTION ALIGN="AUTO"><P>You will not infringe copyright as long as you don't make money from the use of the materials.</P></QUESTION> <BUTTON>True</BUTTON> <BUTTON>False</BUTTON> <FEEDBACK BUTTON="1" DETAIL="1" GRADE="WRONG"><P><B>Incorrect</B>. Generally speaking, copyright protections apply irrespective of whether money changes hands.</P></FEEDBACK> <FEEDBACK BUTTON="2" DETAIL="1" GRADE="RIGHT"><P><B>Correct</B>. The reuse of all rights reserved materials without permission of the copyright holder for non-profit purposes would constitute a breach of copyright.</P></FEEDBACK> </PAGE> 
<PAGE ID="Question 6" TYPE="Multiple Choice" STYLE="Choose Buttons" NEXTPAGE="Question 7" NEXTPAGEDISABLED="False" SCORING="Totals" SORTNAME="Question    6"><QUESTION ALIGN="AUTO"><P>If there is no copyright symbol or notice, then you can assume the work is copyright free.</P></QUESTION> <BUTTON>True</BUTTON> <BUTTON>False</BUTTON> <FEEDBACK BUTTON="1" DETAIL="1" GRADE="WRONG"><P><B>Incorrect</B>. The absence of a copyright notice does not mean that holder has abandoned copyright. You should assume the default position of &quot;all rights reserved&quot; copyright.</P></FEEDBACK> <FEEDBACK BUTTON="2" DETAIL="1" GRADE="RIGHT"><P><B>Correct</B>. The default position when the author has not otherwise indicated copyright is that the work is copyrighted as &quot;all rights reserved&quot;. You should generally assume that all works are protected by copyright.</P></FEEDBACK> </PAGE> 
<PAGE ID="Question 7" TYPE="Multiple Choice" STYLE="Choose Buttons" NEXTPAGE="Conclusion" NEXTPAGEDISABLED="False" SCORING="Totals" SORTNAME="Question    7"><QUESTION ALIGN="AUTO"><P>If the materials use a <A HREF="http://creativecommons.org/ ">Creative Commons</A> license, you are free to reuse, adapt, mix and modify these resources for educational purposes.</P></QUESTION> <BUTTON>True</BUTTON> <BUTTON>False</BUTTON> <FEEDBACK BUTTON="1" DETAIL="1" GRADE="WRONG"><P><B>Incorrect</B>. The permissions and restrictions associated with a Creative Commons license depends on the type of Creative Commons license used.</P></FEEDBACK> <FEEDBACK BUTTON="2" DETAIL="1" GRADE="RIGHT"><P>Yes. You must confirm which type of Creative Commons license is used in order to determine the permissions for reuse. </P></FEEDBACK> </PAGE> 
</BOOK>

*/

?>
