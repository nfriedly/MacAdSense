/*
 *
 * Copyright (C) 2007-2009 Kai 'Oswald' Seidler, http://oswaldism.de
 *
 * This program is free software; you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by the Free
 * Software Foundation; either version 2 of the License, or (at your option)
 * any later version.
 * 
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or
 * FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for
 * more details.
 * 
 * You should have received a copy of the GNU General Public License along with
 * this program; if not, write to the Free Software Foundation, Inc., 675 Mass
 * Ave, Cambridge, MA 02139, USA. 
 * 
 */

var version = "0.9";
var timeout = 60*20; // set update frequency to 20 minutes 
var now=0;
var timeoutevent=0;

// many parts of this Widged were based on Apple's Widget tutorial at
// http://developer.apple.com/documentation/AppleApplications/Conceptual/Dashboard_ProgTopics/

function showBack()
{
    var front = document.getElementById("front");
    var back = document.getElementById("back");

    if (window.widget)
        widget.prepareForTransition("ToBack");

    front.style.display="none";
    back.style.display="block";

    if (window.widget)
        setTimeout ('widget.performTransition();', 0);
    

}

function hideBack()
{
    var front = document.getElementById("front");
    var back = document.getElementById("back");

    if (window.widget)
    {
		var form=document.getElementById("ff");
		
	
		if(form.username.value!="" && form.password.value!="" )
		{
			widget.setPreferenceForKey(form.username.value, "username");
			
			
			widget.setPreferenceForKey(form.timeframe.value, "timeframe");
			document.getElementById('big_timeframe').innerHTML = widget.preferenceForKey("timeframe");
	
	
			now=0;
			showDialog("Saving to keychain...");
			var command=widget.system("./MacAdSense.php setcredentials", onshow);
			command.write(form.username.value+"\n");
			command.write(form.password.value+"\n");
			command.close();
	
			form.password.value="";
			
		}
		
		if(widget.preferenceForKey("timeframe") != form.timeframe.value){
		
			showDialog("Loading...");
			
			widget.setPreferenceForKey(form.timeframe.value, "timeframe");
			document.getElementById('big_timeframe').innerHTML = widget.preferenceForKey("timeframe");
		
		}
		
		fetchData();
	
    }


    if (window.widget)
        widget.prepareForTransition("ToFront");
        

    back.style.display="none";
    front.style.display="block";

    if (window.widget)
    {
        setTimeout ('widget.performTransition();', 0);
        
    }
}

function showDialog(message)
{
	dialog_content.innerHTML=message;
	dialog.style.display="block";
	front_content.style.display="none";
}

function hideDialog()
{
	front_content.style.display="block";
	dialog.style.display="none";
}

function endHandler()
{
}

function fetchData()
{
	var command=widget.system("./MacAdSense.php getdata " + 
		widget.preferenceForKey("username") + " " + 
		widget.preferenceForKey("timeframe"), displayData);
	command.close();
}

function displayData(data)
{
	//output=data.outputString.split("#");
	
	output = JSON.parse(data.outputString);

	// do we get real data
	//if(output[1]!=0)
	if(output.time)
	{
	
		document.getElementById("updated").innerHTML=output.time; // 0
		document.getElementById("timeframe").innerHTML=output.input.timeframe; //1
		//document.getElementById("extrapolated").innerHTML="$"+output[1];
		document.getElementById("clicks").innerHTML=output.clicks; //3
		document.getElementById("earnings").innerHTML="$"+output.usd; // 2
		document.getElementById("ecpm").innerHTML="$"+output.ecpm; //5

		now = Math.round(new Date().getTime()/1000);

		hideDialog();
	}
	else
	{
		showDialog("Can't fetch AdSense data.<br><span class=\"small\">Maybe wrong credentials or network problems?</span>");
	}

	if(timeoutevent!=0)
	{
		clearTimeout(timeoutevent);
	}
	timeoutevent=setTimeout('fetchData();',timeout*1000);
}

function onshow()
{
	if(widget.preferenceForKey("username")!=undefined && widget.preferenceForKey("username")!="")
	{
		if(now && Math.round(new Date().getTime()/1000)-now<timeout)
		{
			// still not the time to fetch new adsense data
		}
		else
		{
        	showDialog("Loading...");

			setTimeout('fetchData()',100);
			
			document.getElementById('big_timeframe').innerHTML = widget.preferenceForKey("timeframe");
		}
	}
	else
	{
  		showDialog("Please set username and<br>password on the back side.");
	}
}

function onhide()
{
}

function setup()
{
	if (window.widget) 
	{
		widget.onshow = onshow;
		widget.onhide = onhide;

		var form=document.getElementById("ff");
		form.username.value=widget.preferenceForKey("username");
		
		// selecte the appropriate timeframe
		for(var ops = form.timeframe.options, i=0; i< ops.length; i++){
			if(ops[i].value == widget.preferenceForKey("timeframe")){
				ops.selectedIndex = i;
			}
		}
		
	}
	var done_button = new AppleGlassButton(document.getElementById("done"), "Done", hideBack);
	i_button = new AppleInfoButton(document.getElementById("i"), document.getElementById("front"), "white", "white", showBack);
	document.getElementById("version").innerHTML=version;
	
	var dialog = document.getElementById("dialog");
	var dialog_content = document.getElementById("dialog_content");
	var front_content = document.getElementById("front_content");
	
	document.getElementById("front_content").onclick = function(){
	        showDialog("Loading...");
			setTimeout('fetchData()',100);
	};

}
