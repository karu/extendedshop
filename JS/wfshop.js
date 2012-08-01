function dettagli(id) {
	if($("#"+id).is(":hidden")) {
		$("#"+id).slideDown("slow");
	}else{
		$("#"+id).slideUp("slow");
	}
}

function addCart() {
	$("#shop_minibasket").css({backgroundColor:"#FFEBAE",border:"1px solid #FFCC33"})
	$("#shop_minibasket").animate({padding:"1em"})
	$("#shop_minibasket_message").slideDown("normal");
}
function addCart2() {
	$("#shop_nero").animate({opacity:0.7,height:"100%"},500);
	$("#shop_minibasket_message2").fadeIn("slow");
}

function closeThis() {
	$("#shop_nero").animate({opacity:0,height:"0"},500);
	$("#shop_minibasket_message2").fadeOut("slow");
}
function closeCart() {
	$("#shop_minibasket_message").slideUp("normal");
	$("#shop_minibasket").css({backgroundColor:"transparent",border:"none"})
	$("#shop_minibasket").animate({padding:"0"})
}

$(function() {

    $(".related_products").accessNews({
        newsHeadline: "Prodotti correlati",
        newsSpeed: "slow"
    });


});

window.onload = function() {
	$(".anteprima").hide();
	$("#shop_minibasket_message").hide();
	$(".ajax-preview").css({fontSize:"9px",textTransform:"uppercase"});
	$("#shop_nero").css({opacity:0,backgroundColor:"#000"});
	$("#shop_loading").css({opacity:0,backgroundColor:"#000"});
}


function showCorrelated()	{
	$(".related_products").accessNews({
        newsHeadline: "Prodotti correlati",
        newsSpeed: "slow"
    });
}

