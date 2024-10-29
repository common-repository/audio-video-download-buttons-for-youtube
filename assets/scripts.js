const dbfy_download_in_action = {};
function dbfy_download(event_, id)
{
	event_.preventDefault();
	const button = event_.target;
	const parentContainer = button.parentNode;
	const downloadButton = parentContainer.querySelector(".downloadButton");
	let classN = " shown";
	let hadeButtonClass = " faded";
	if (parentContainer.className.indexOf(classN) >= 0 )
	{
		parentContainer.className= parentContainer.className.replace(classN,''); 
		downloadButton.className= downloadButton.className.replace(hadeButtonClass,''); 
		return;
	} else {
		parentContainer.className = parentContainer.className + classN; 
		downloadButton.className= downloadButton.className + hadeButtonClass; 
	}
	
	if (id in dbfy_download_in_action){
		alert("Download already in process, please try again shortly");
		return;
	}
	dbfy_download_in_action[id] = true;
	
	let url = location.href;
	let params = { "dbfy_download": id }; 
	const area = parentContainer.querySelector(".downloadButtons");
	area.innerHTML =  "Loading ...";

	return fetch(url, {
		method: "post",
		body:JSON.stringify(params)
	}).then(function(response) {
		return response.text().then(function(text) {
			delete dbfy_download_in_action[id];
			area.innerHTML = dbfy_parse_response (text, event_, id);
		});
	}).catch(ex=>{
		alert(ex.message);
		delete dbfy_download_in_action[id];
	});
}


function dbfy_parse_response(text, event_, id)
{
	let content = '';
	try {
		let continue_=false;
		let obj = null;
		try{
			obj = JSON.parse(text);
			continue_ = true;
		} catch (e) {
			content = "Unexpected response. Try again or contact admin:<br/>"+ text;
		}
		if ( !obj.error)
		{
			try
			{
				content += '<div class="container1">';
				for (const [avKey, VidAudArrays] of Object.entries(obj.data))
				{
					content += `<div class="av_container ${avKey}">  <span class="title">${avKey}</span>`;
					content += `<div class="inner2">`;
					content += `<div class="headerRow"> <span class="url"></span><span class="prop contentLength">MB</span> <span class="prop mimeType">ext</span>  <span class="prop qualityLabel"><a href="javascript:alert(\'Quality - in generic talks/podcasts, you might not notice a significant difference between high/low quality audios\')";>Q</a></span></div>`;
					let sortedByCapacity = VidAudArrays.sort(function(a, b) { return b.filesize - a.filesize; });
					for (const obj of sortedByCapacity)
					{
						const qualityLab = obj.quality.replace('ultralow', 'ul').replace('low', 'l').replace('medium', 'm').replace('high', 'h').replace('ultrahigh', 'uh');
						content += 
						`<div class="eachRow">
							<span class="prop url"><a href="${obj.url}" target="_blank">⭳</a></span>
							<span class="prop contentLength">${obj.filesize.toFixed(1)}</span>
							<span class="prop mimeType">${obj.ext}</span>
							<span class="prop qualityLabel">${qualityLab}</span>
						</div>`;
					}
					content += `</div>`;
					content += '</div>';
				}
				
				content += '</div>';
				let warningMsg = '<span class="yt_terms_comply">[Download is meant to be in compliance with <a href="https://www.youtube.com/static?template=terms" target="_blank">Youtube Terms</a> and <a href="https://www.youtube.com/howyoutubeworks/policies/copyright/#fair-use" target="_blank">Fair Use</a>]</span>';
				// container1
				content += warningMsg;
			} catch (e) {
				content = "Site has problems. Contact admin."+ e.message;
			}
		}
		else{
			content = obj.data;
		}
	} catch (e) {
		content = "Website can not return response. Try again or contact admin:<br/>"+ e.message;
	}
	return content;
}