window.addEvent('domready', function() {
	tweets = $$('div.tx-twittersearch-pi1 ul.listing li.entry');
	tweets.addEvent(
		'mouseenter', function(event) {
			/*var morph = new Fx.Morph(this);
			morph.start({
				'background-color':['#EEEEEE'],
				/*'border-color':'#000000',
			});*/
			this.style.backgroundColor = '#EEEEEE';
			this.style.borderColor = '#000000';
		});
	tweets.addEvent(
		'mouseleave', function(event) {
/*			var morph = new Fx.Morph(this);
			morph.start({
				'background-color':['#FFFFFF'],
				'border-color':'#AAAAAA',
			});*/
			this.style.backgroundColor = '#FFFFFF';
			this.style.borderColor = '#AAAAAA';
		});
});
