/**
 * main.js
 * 
 * @author Niels Vornholt <nv@holzmann-medien.de>
 * @copyright 2016 Holzmann Medien GmbH & Co. KG
 * @description "Bootstrap-Datei" des JS-Backbones für dhz.net.
 * !!NEVER USE IT UNMINIFIED!!
 * Diese Datei wird mittels des GRUNT-Tasks vor Production-Einsatz getestet, zusammengefuehrt und minified.
 * Versionierung: Siehe SVN 
 * 
 * @see gruntfile.js
 * @target main.min.js
 *
 * @todo Videotracking finalisieren (trackVideo())
 */




/**
 * Initialisierung von Variablen.
 */
pollPlot = "";

globalViewport = "";

/**
 * Kuemmert sich um das Verschieben des Top-Themas bei Verwendung der Groesse "Desktop small".
 * Das Top Thema muss in diesem Fall aus dem <main> heraus, und in den .container reingeschoben werden.
 * Wird eine andere Groesse angefordert (z.B. durch Resizing), wird das Top Thema wieder in den <main>-Container geschoben.
 *
 * Zusaetzlich wird das dritte Bild der Artikelliste bei grosser Desktopansicht ein-, und in allen anderen Ansichten ausgeblendet.
 * 
 * @param  {jQuery-Instanz}
 * @param  {gerade aktiver Viewport, eines aus "xs","sm","md","lg"}
 * @return {void/none}
 */
(function($, viewport){

	globalViewport = viewport;	

	// Den Viewport bei initial-Aufruf abspeichern. Grund: Wenn der Viewport sich nicht ändert, brauchen keine Banner verschoben werden.
	var storedViewport = viewport.current();
	var calledViewports = [];
	calledViewports.push(storedViewport);
	
	//Setzt das Cookie mit welchem Devicetype die Site aktuell aufgerufen wird (smartphone, tablet, desktop)
	if($.cookie("DEVICETYPE") == null) {
		$.ajax('/?event=cmp.cst.seitenstruktur.devicedetection&nocache=1').done(function(data){
			if($.trim(data) === 'tablet') {
				$('#sky-left').remove();
				$('#sky-right').remove();
				$('#billboard').remove();
			}
		});
	}
	else if($.cookie("DEVICETYPE") === 'tablet') {
		$('#sky-left').remove();
		$('#sky-right').remove();
		$('#billboard').remove();
	}

	if( viewport.is('md') ) {
		// Top-Thema wird aus <main> rausgenommen und VOR <main> wieder eingefuegt.
		$('article#top').insertBefore('div.container main');
	}

	if(!viewport.is('lg')) {
		/* Alles, ausser Desktop large */

		// Drittes Bild in jeder Artikelliste wird ausgeblendet, anschliessend wird die Sortierung per masonry erneut ausgefuehrt (Platzverhaeltnisse haben sich geaendert).
		$("main section.iso").each(function(){
			$(this).find("article.article-teaser:eq(2) img").hide();
		});
	}

	// Alles in dieser Funktion wird jedes Mal ausgefuehrt, wenn sich die Fenstergroesse aendert.
	$(window).bind('resize', function() {
		
		viewport.changed(function(){

			if (viewport.current() != storedViewport) {
			
				/*
				 * Handling Top Thema und Artikelliste
				 */
				if( viewport.is('md') ) {
					/* Desktop small */

					// Top-Thema wird aus <main> rausgenommen und VOR <main> wieder eingefuegt.
					$('article#top').insertBefore('div.container main');
				}

				if( !viewport.is('md') && !$('main article#top').length) {
					/* Alle Viewports ausser Desktop small, Top Thema ist NICHT im <main> */

					// Top Thema wird als erstes Kind ins <main> geschoben.
					$('article#top').prependTo('#mainrow');
				}

				if(!viewport.is('lg')) {
					/* Alles, ausser Desktop large */

					// Drittes Bild in jeder Artikelliste wird ausgeblendet, anschliessend wird die Sortierung per masonry erneut ausgefuehrt (Platzverhaeltnisse haben sich geaendert).
					$("main section.iso").each(function(){
						$(this).find("article.article-teaser:eq(2) img").hide(0, function(){
							$articleContainer.masonry('layout');
						});
					});
				}
				else {
					/* Desktop Large */

					// Drittes Bild jeder Artikelliste einblenden
					$("main section.iso").each(function(){
						$(this).find("article.article-teaser:eq(2) img").show();
					});
				}

				/**
				 * Banner neu laden
				 */
				handleAdBanners(viewport, 'reload', calledViewports);

				/**
				 * Falls ein jqPlot-Objekt (Umfragen!) exisitert, neu zeichnen
				 */
				if(typeof(pollPlot) === 'object') {
					pollPlot.replot();
				}

				storedViewport = viewport.current();
				/**
				 * Speichert bereits geladene Viewports in ein Array ab
				 */
				if ($.inArray(storedViewport, calledViewports) == -1)
				{
					calledViewports.push(storedViewport);
				}
			}
		});
	});

	// Alles in dieser Funktion wird ausgefuehrt, nachdem das Dokument komplett geladen ist.
	$(document).ready(function() {
		//Allen Bannerslots die richtigen Bootstrap Anzeigeklassen geben
		$('#' + tabletRenderslots.join(', #')).addClass('visible-sm-block');
		$('#' + desktopRenderslots.join(', #')).addClass('visible-md-block visible-lg-block');
		$('#' + mobileRenderslots.join(', #')).addClass('visible-xs-block');
		$('.superbanner-list').addClass('visible-sm-block');

		// Alle Banner laden. Ladezeitpunkt erfolgt, nachdem die komplette Seite geladen wurde.
		handleAdBanners(viewport, 'load');
		adition.srq.push(function(api){	api.completeRendering() });

		// Tortengrafiken mittels jqPlot fuer Polls
		if($("#pollPie").length){
			drawPollChart();
		}
	});
})(jQuery, ResponsiveBootstrapToolkit);


/**
 * Kuemmert sich um die optimale Verteilung der Artikel-Teaser innerhalb von <main> > <section .iso>.
 * Da Artikel-Teaser aufgrund ihrer Textmenge unterschiedlich lang sein koennen, wuerden bei normalem CSS-Float
 * weisse Luecken zwischen den Teasern entstehen. Dies wird mittels masonry verhindert.
 * @param  {jQuery-Instanz}
 * @return {void/none}
 * @see  http://masonry.desandro.com/
 */
$( function() {
	$articleContainer = $('#mainrow .iso, #mainrow .iso2');
	$articleContainer.masonry({
		itemSelector: '.article-teaser, .grid-item, .box',
	});

	$asideContainer = $('aside.iso .row');
	$asideContainer.masonry({
		itemSelector: '.box, .grid-item'
	});
});


/**
 * Handling von Carousels, z.B. Bildergalerien, Mediengalerien.
 * 
 */
$(document).ready(function(){
	$('.carousel-slick').slick({
		dots: false,
		infinite: true,
		speed: 300,
		slidesToShow: 1,
		slidesToScroll: 1,
		arrows: true,
		lazyLoad: 'ondemand',
		mobileFirst: true,
		prevArrow: '<button type="button" class="slick-prev"><span class="glyphicon glyphicon-triangle-left"></span>Previous</button>',
		nextArrow: '<button type="button" class="slick-next"><span class="glyphicon glyphicon-triangle-right"></span>Next</button>',
		responsive: [
			{
				breakpoint: 370,
				settings: {
					slidesToShow: 2,
				}
			},
			{
				breakpoint: 470,
				settings: {
					slidesToShow: 3,
				}
			},
			{
				breakpoint: 960,
				settings: {
					slidesToShow: 2,
				}
			},
			{
				breakpoint: 1280,
				settings: {
					slidesToShow: 4,
				}
			}
		]
	});

	/* Detailansicht von Bildergalerien */
	$('figure #imagecontainer').slick({
		dots: true,
		infinite: true,
		speed: 300,
		slidesToShow: 1,
		slidesToScroll: 1,
		arrows: true,
		mobileFirst: true,
		lazyLoad: 'ondemand',
		prevArrow: '<button type="button" class="slick-prev"><span class="glyphicon glyphicon-triangle-left"></span>Previous</button>',
		nextArrow: '<button type="button" class="slick-next"><span class="glyphicon glyphicon-triangle-right"></span>Next</button>',
	});
	
	$('figure #imagecontainer').on('swipe', function(event, slick, direction){
  		countClicks();
  	});
  	$('figure #imagecontainer > button').click(function(){
  		countClicks();
  	});
});


/**
 * Laedt den Inhalt einer URL in das Modal-Layer, welches in jeder Seite zur Verfuegung steht. 
 * Bereits im DOM-Knoten ".modal-content" befindlicher Content wird ersetzt.
 * @param  {string} url  der zu ladende URL. Wird per Default mittels GET geladen.
 * @param  {object} data Optional: Object mit Daten. Ist das Object vorhanden, wird der URL mittels POST aufgerufen und die Daten mitgeschickt.
 * @return {void}      none
 */
function loadModal(url, data) {
	if(data){
		$('#imageModal').modal('show').find(".modal-content").load(url, data);
	}
	else {
		$('#imageModal').modal('show').find(".modal-content").load(encodeURI(url));
	}
}


/**
 * Sobald eine Bildergalerie im Modal-Layer aufgerufen wird, muss sie nachtraeglich "geslicked" werden, 
 * da das Markup erst nach document.ready() eingefuegt wird. Diese Funktion tut genau das.
 * Als Bugfix muss erstmalig slickGoTo aufgerufen werden, ansonsten wird das erste Slide nicht korrekt angezeigt.
 * 
 * @var {int+} Nummer des Slides, welches initial angezeigt werden soll.
 * @return {void} none
 */
function slickModal(slide){
	$('.modal-body figure #imagecontainer').slick({
		dots: true,
		infinite: true,
		speed: 300,
		slidesToShow: 1,
		slidesToScroll: 1,
		arrows: true,
		mobileFirst: true,
		lazyLoad: 'ondemand',
		prevArrow: '<button type="button" class="slick-prev"><span class="glyphicon glyphicon-triangle-left"></span>Previous</button>',
		nextArrow: '<button type="button" class="slick-next"><span class="glyphicon glyphicon-triangle-right"></span>Next</button>',
	});
	
	$('.modal-body figure #imagecontainer').on('swipe', function(event, slick, direction){
  		countClicks();
  	});
  	$('.modal-body figure #imagecontainer > button').click(function(){
  		countClicks();
  	});
  	$('.modal-body figure #imagecontainer').slick('slickGoTo', slide);
}


/**
 * Ist eine Sticky-Ad vorhanden? Falls ja, dann Close-Button dazu anzeigen
 * @param  {[type]} viewport [xs oder sm, nur hier gibt es Sticky Ads]
 * @return {[type]}          void (none)
 */
function showStickyClose(viewport) {	
	var intervalID = setInterval(function() {
		//  Wird alle 3 sek ausgeführt
		if($('#sticky_'+viewport+' div.adWrapper div').length) {
			$('#sticky_'+viewport+' span.glyphicon.hidden').addClass('visible-'+viewport).removeClass('hidden');
		}
		else {
			$('#sticky_'+viewport+' span.glyphicon.hidden').addClass('hidden').removeClass('visible-'+viewport);	
		}
	}, 3000);
	setTimeout(function() {
		clearInterval(intervalID);
	}, 12000);
}


/**
 * Blendet - je nach Viewport - Bannerplaetze ein oder aus und laedt die betroffenen Plaetze neu, damit auch eine Ad Impression gezaehlt wird,
 * sobald ein Banner neu in den Viewport kommt.
 * @param  {object} viewport Informationen ueber den derzeitigen Viewport, geliefert vom Responsive Bootstrap Toolkit.
 * @param  {string} method   'load' oder 'reload'. Entscheidet, ob die Inhalte der eingeblendeten Bannerplaetze neu geladen werden sollen oder nicht.
 * @return {[type]}          void (none)
 */
function handleAdBanners(viewport, method, calledViewports) {
	if (calledViewports === 'undefined') {
		calledViewports = [];
	}
	if(viewport.is('xs')){
		// Werbemittel f. Smartphone laden, falls noch nicht geschehen
		if(method == 'reload' && $.inArray('xs', calledViewports) == -1) {
			adition.srq.push(function(api){ api.reload(mobileRenderslots); });
		}
		else if (method == 'load') {
			adition.srq.push(function(api){ api.load(mobileRenderslots); });
		}
		// Ist eine Sticky-Ad vorhanden? Falls ja, dann Close-Button dazu anzeigen
		showStickyClose('xs');
	}
	if(viewport.is('sm')){
		// Werbemittel f. Tablet laden, falls noch nicht geschehen
		if(method == 'reload' && $.inArray('sm', calledViewports) == -1) {
			adition.srq.push(function(api){ api.reload(tabletRenderslots); });
		}
		if (method == 'load') {
			adition.srq.push(function(api){ api.load(tabletRenderslots); });
		}
		// Ist eine Sticky-Ad vorhanden? Falls ja, dann Close-Button dazu anzeigen
		showStickyClose('sm');
	}
	if(viewport.is('md') || viewport.is('lg')){
		// Werbemittel f. Desktop laden, falls noch nicht geschehen
		if(method == 'reload' && $.inArray('md', calledViewports) == -1 && $.inArray('lg', calledViewports) == -1) {
			adition.srq.push(function(api){ api.reload(desktopRenderslots); });
		}
		if (method == 'load') {
			adition.srq.push(function(api){ api.load(desktopRenderslots); });
		}
	}
	$('#mainrow .iso').masonry().masonry('layout');
}


/**
 * Zaehlt eine Page Impression in eTracker und IVW fuer die aktuell im Browser dargestellte Seite.
 * Wird nur fuer JS-Aufrufe verwendet (z.B. Callback fuer AJAX-Requests).
 * Parameter fuer eTracker und IVW kommen aus der Seitenstruktur (dspTracking).
 * Es ist möglich, die Paramenter mittels inline JS zu überschreiben, es wird geprüft, ob entsprechende Variablen gesetzt sind, oder nicht.
 * Zusätzlich können der Funktion direkt Parameter übergeben werden, die dann Priorität über allen global gesetzten Variablen haben.
 * 
 * @var {string} p_et_pagename, Seitenname, der in eTracker erscheinen soll
 * @var {string} p_et_areas, Bereich, der in eTracker erscheinen soll
 * @var {string} p_et_url, URL, die in eTracker verwendet werden soll
 * @return {void} none
 */
function countClicks(p_et_pagename, p_et_areas, p_et_url){
	if(typeof alt_et_pagename !== 'undefined')
		var pagename = alt_et_pagename;
	else if (typeof p_et_pagename !== 'undefined') 
		var pagename = p_et_pagename;
	else 
		pagename = et_pagename;

	if(typeof alt_et_areas !== 'undefined')
		var areas = alt_et_areas;
	else if (typeof p_et_areas !== 'undefined') 
		var areas = p_et_areas;
	else 
		areas = et_areas;

	if(typeof alt_et_url !== 'undefined')
		var url = alt_et_url;
	else if (typeof p_et_url !== 'undefined') 
		var url = p_et_url;
	else 
		url = et_url;

	et_eC_Wrapper(	et_code,
					decodeURIComponent(pagename.replace(/\+/g, ' ')),
					decodeURIComponent(areas.replace(/\+/g, ' ')), 
					"", 
					decodeURIComponent(url.replace(/\+/g, ' ')), 
					"", 
					"", 
					"", 
					"", 
					"", 
					"", 
					"", 
					"", 
					"", 
					""
				);
	iom.c(iam_data,1);
}


/**
 * Leitet den Speichervorgang des Empfehlen-Formulars fuer Multimedia-Inhalte ein.
 * Userdaten werden aus dem DOM ausgelesen und mittels POST versendet.
 * @param {string} loadUrl Der URL, der via POST aufgerufen werden soll.
 * @return {void} none
 */
function saveRecomendForm(loadUrl){
	//var loadUrl = "/?event=cmp.cst.multimedia.getmultimediarecommendform";

	var data = { 	subject: $("#multimediaRecommendForm input#tsubject").val(),
					sender: $("#multimediaRecommendForm input#sender").val(),
					receiver: $("#multimediaRecommendForm input#receiver").val(),
					message: $("#multimediaRecommendForm textarea#message").val(),
					captcha: $("#multimediaRecommendForm input#reg_captcha").val(),
					captchacrypt: $("#multimediaRecommendForm input#captchacrypt").val(),
					id: $("#multimediaRecommendForm input#id").val(),
					url: $("a#recommend").attr("data-static-url"),
					dataresource: $("#multimediaRecommendForm input#dataresource").val(),
					send: 1
				};
	loadModal(loadUrl, data);
}


/**
 * Erstellt mit Hilfe von jqPlot eine Tortengrafik, um die Ergebnisse einer einfachen Umfrage darzustellen
 * @param  {[type]} data [description]
 * @return {[type]}      [description]
 */
function drawPollChart(data){
	$(document).ready(function(){
		pollPlot = $.jqplot('pollPie', pollData, {
			seriesDefaults:{ 
				renderer:$.jqplot.PieRenderer, 
				trendline:{ show: true },
				rendererOptions: { padding: 8, showDataLabels: true },
				shadow: false,
				seriesColors: ["#065784", "#5cb85c", "#5bc0de", "#f0ad4e", "#d9534f", "#0c9688", "#aded51", "#8859e2", "#bc57e5", "#0e9645", "#cccccc", "#aaaaaa"]
			},
			grid:{
				background: 'transparent',
				shadow: false,
				borderWidth: 0
			},
			legend:{ 
				show: true,
				placement: 'inside',
				location: 's',
				border: 'none'
			}
		});
	});
}


/**
 *	Datepicker
 *	Input-Felder mit uebergeordneter Klasse .date bekommen den Datepicker aktiviert.
 */
$('.date').datepicker({
	format: "dd.mm.yyyy",
	language: "de",
	autoclose: true,
	keyboardNavigation: false,
	todayHighlight: true,
	disableTouchKeyboard: true 
});


/**
 * Öffnet ein Browser Popup Window
 * 
 * @param  {string} url  [URL die geöffnet werden soll]
 * @param  {string} name Name des Fensters (js)
 * @param  {[type]} wdt  Breite des Fensters
 * @param  {[type]} hgt  Hoehe des Fensters
 * @return {[void]}      none
 */
function popup(url, name, wdt, hgt) {
	window.open(url, name, "width="+ wdt +",height=" + hgt + ",scrollbars=yes,menubar=no,resizable=true");
}

/**
 * StickyAd verschwinden lassen
 * @return {[void]}      none
 */
function removeSticky(){
	$('div.stickySP').hide();
}

/**
 * event handler zum tracken von Videos.
 * 
 * @param  {event} e 
 * @return {void}   none
 */
function trackVideo(e){
	console.log('Video play');

}