<?php
if (!defined ("TYPO3_MODE")) 	die ("Access denied.");

$TCA["tx_extendedshop_status"] = Array (
    "ctrl" => $TCA["tx_extendedshop_status"]["ctrl"],
    "interface" => Array (
        "showRecordFieldList" => "sys_language_uid,l18n_parent,l18n_diffsource,hidden,status,priority"
    ),
    "feInterface" => $TCA["tx_extendedshop_status"]["feInterface"],
    "columns" => Array (
        'sys_language_uid' => Array (        
            'exclude' => 1,
            'label' => 'LLL:EXT:lang/locallang_general.php:LGL.language',
            'config' => Array (
                'type' => 'select',
                'foreign_table' => 'sys_language',
                'foreign_table_where' => 'ORDER BY sys_language.title',
                'items' => Array(
                    Array('LLL:EXT:lang/locallang_general.php:LGL.allLanguages',-1),
                    Array('LLL:EXT:lang/locallang_general.php:LGL.default_value',0)
                )
            )
        ),
        'l18n_parent' => Array (        
            'displayCond' => 'FIELD:sys_language_uid:>:0',
            'exclude' => 1,
            'label' => 'LLL:EXT:lang/locallang_general.php:LGL.l18n_parent',
            'config' => Array (
                'type' => 'select',
                'items' => Array (
                    Array('', 0),
                ),
                'foreign_table' => 'tx_extendedshop_status',
                'foreign_table_where' => 'AND tx_extendedshop_status.pid=###CURRENT_PID### AND tx_extendedshop_status.sys_language_uid IN (-1,0)',
            )
        ),
        'l18n_diffsource' => Array (        
            'config' => Array (
                'type' => 'passthrough'
            )
        ),
        "hidden" => Array (        
            "exclude" => 1,
            "label" => "LLL:EXT:lang/locallang_general.php:LGL.hidden",
            "config" => Array (
                "type" => "check",
                "default" => "0"
            )
        ),
        "status" => Array (        
            "exclude" => 1,        
            "label" => "LLL:EXT:extendedshop/locallang_db.php:tx_extendedshop_status.status",        
            "config" => Array (
                "type" => "input",    
                "size" => "20",    
                "max" => "20",    
                "eval" => "required,trim,unique",
            )
        ),
        "priority" => Array (        
            "exclude" => 1,        
            "label" => "LLL:EXT:extendedshop/locallang_db.php:tx_extendedshop_status.priority",        
            "config" => Array (
                "type" => "input",    
                "size" => "5",    
                "max" => "3",    
                "range" => Array ("lower"=>0,"upper"=>1000),    
                "eval" => "int,unique",
            )
        ),
    ),
    "types" => Array (
        "0" => Array("showitem" => "sys_language_uid;;;;1-1-1, l18n_parent, l18n_diffsource, hidden;;1, status, priority")
    ),
    "palettes" => Array (
        "1" => Array("showitem" => "")
    )
);

$TCA["tx_extendedshop_orders"] = Array (
	"ctrl" => $TCA["tx_extendedshop_orders"]["ctrl"],
	"interface" => Array (
		"showRecordFieldList" => "hidden,code,customer,shippingcustomer,date,shipping,payment,total,total_notax,weight,volume,trackingcode,state,ip,note,status,deliverydate,shipping_cost,payment_cost,shipping_tracking"
	),
	"feInterface" => $TCA["tx_extendedshop_orders"]["feInterface"],
	"columns" => Array (
		"hidden" => Array (		
			"exclude" => 1,
			"label" => "LLL:EXT:lang/locallang_general.php:LGL.hidden",
			"config" => Array (
				"type" => "check",
				"default" => "0"
			)
		),
		"code" => Array (		
			"exclude" => 1,		
			"label" => "LLL:EXT:extendedshop/locallang_db.php:tx_extendedshop_orders.code",		
			"config" => Array (
				"type" => "input",	
				"size" => "15",	
				"max" => "50",	
				"eval" => "required,trim",
			)
		),
		"customer" => Array (		
			"exclude" => 1,		
			"label" => "LLL:EXT:extendedshop/locallang_db.php:tx_extendedshop_orders.customer",		
			"config" => Array (
				"type" => "group",	
				"internal_type" => "db",	
				"allowed" => "fe_users",	
				"size" => 1,	
				"minitems" => 0,
				"maxitems" => 1,
			)
		),
		"shippingcustomer" => Array (		
			"exclude" => 1,		
			"label" => "LLL:EXT:extendedshop/locallang_db.php:tx_extendedshop_orders.shippingcustomer",		
			"config" => Array (
				"type" => "group",	
				"internal_type" => "db",	
				"allowed" => "tt_address",	
				"size" => 1,	
				"minitems" => 0,
				"maxitems" => 1,
			)
		),
		"date" => Array (		
			"exclude" => 1,		
			"label" => "LLL:EXT:extendedshop/locallang_db.php:tx_extendedshop_orders.date",		
			"config" => Array (
				"type" => "input",
				"size" => "12",
				"max" => "20",
				"eval" => "datetime",
				"checkbox" => "0",
				"default" => "0"
			)
		),
		"shipping" => Array (		
			"exclude" => 1,		
			"label" => "LLL:EXT:extendedshop/locallang_db.php:tx_extendedshop_orders.shipping",		
			"config" => Array (
				"type" => "text",
				"cols" => "30",	
				"rows" => "3",
			)
		),
		"payment" => Array (		
			"exclude" => 1,		
			"label" => "LLL:EXT:extendedshop/locallang_db.php:tx_extendedshop_orders.payment",		
			"config" => Array (
				"type" => "text",
				"cols" => "30",	
				"rows" => "3",
			)
		),
		"total" => Array (		
			"exclude" => 1,		
			"label" => "LLL:EXT:extendedshop/locallang_db.php:tx_extendedshop_orders.total",		
			"config" => Array (
				"type" => "input",	
				"size" => "5",	
				"eval" => "required",
			)
		),
		"total_notax" => Array (		
			"exclude" => 1,		
			"label" => "LLL:EXT:extendedshop/locallang_db.php:tx_extendedshop_orders.total_notax",		
			"config" => Array (
				"type" => "input",	
				"size" => "5",	
				"eval" => "required",
			)
		),
		"weight" => Array (		
			"exclude" => 1,		
			"label" => "LLL:EXT:extendedshop/locallang_db.php:tx_extendedshop_orders.weight",		
			"config" => Array (
				"type" => "input",	
				"size" => "5",
			)
		),
		"volume" => Array (		
			"exclude" => 1,		
			"label" => "LLL:EXT:extendedshop/locallang_db.php:tx_extendedshop_orders.volume",		
			"config" => Array (
				"type" => "input",	
				"size" => "5",
			)
		),
		"trackingcode" => Array (		
			"exclude" => 1,		
			"label" => "LLL:EXT:extendedshop/locallang_db.php:tx_extendedshop_orders.trackingcode",		
			"config" => Array (
				"type" => "input",	
				"size" => "15",	
				"max" => "50",	
				"eval" => "trim",
			)
		),
		"state" => Array (		
			"exclude" => 1,		
			"label" => "LLL:EXT:extendedshop/locallang_db.php:tx_extendedshop_orders.state",		
			"config" => Array (
				"type" => "input",	
				"size" => "15",	
				"max" => "15",	
				"eval" => "trim",
			)
		),
		"ip" => Array (		
			"exclude" => 1,		
			"label" => "LLL:EXT:extendedshop/locallang_db.php:tx_extendedshop_orders.ip",		
			"config" => Array (
				"type" => "none",
			)
		),
		"note" => Array (		
			"exclude" => 1,		
			"label" => "LLL:EXT:extendedshop/locallang_db.php:tx_extendedshop_orders.note",		
			"config" => Array (
				"type" => "text",
				"cols" => "30",	
				"rows" => "5",
			)
		),
		"status" => Array (        
            "exclude" => 1,        
            "label" => "LLL:EXT:extendedshop/locallang_db.php:tx_extendedshop_orders.status",        
            "config" => Array (
                "type" => "select",    
                "items" => Array (
                    Array("",0),
                ),
                "foreign_table" => "tx_extendedshop_status",    
                "foreign_table_where" => "AND tx_extendedshop_status.sys_language_uid=0 ORDER BY tx_extendedshop_status.priority",    
                "size" => 1,    
                "minitems" => 0,
                "maxitems" => 1,    
            )
        ),
        "deliverydate" => Array (        
            "exclude" => 1,        
            "label" => "LLL:EXT:extendedshop/locallang_db.php:tx_extendedshop_orders.deliverydate",        
            "config" => Array (
                "type" => "input",
                "size" => "8",
                "max" => "20",
                "eval" => "date",
                "checkbox" => "0",
                "default" => "0"
            )
        ),
		"complete" => Array (        
            "exclude" => 1,        
            "label" => "LLL:EXT:extendedshop/locallang_db.php:tx_extendedshop_orders.complete",        
            "config" => Array (
                "type" => "check",
            )
        ),
		"ordernote" => Array (        
            "exclude" => 1,        
            "label" => "LLL:EXT:extendedshop/locallang_db.php:tx_extendedshop_orders.ordernote",        
            "config" => Array (
                "type" => "text",
                "cols" => "30",    
                "rows" => "5",
            )
        ),
        "shipping_cost" => Array (		
			"exclude" => 1,		
			"label" => "LLL:EXT:extendedshop/locallang_db.php:tx_extendedshop_orders.shipping_cost",		
			"config" => Array (
				"type" => "input",	
				"size" => "5",	
				"eval" => "required",
			)
		),
		"payment_cost" => Array (		
			"exclude" => 1,		
			"label" => "LLL:EXT:extendedshop/locallang_db.php:tx_extendedshop_orders.payment_cost",		
			"config" => Array (
				"type" => "input",	
				"size" => "5",	
				"eval" => "required",
			)
		),
		"shipping_tracking" => Array (		
			"exclude" => 1,		
			"label" => "LLL:EXT:extendedshop/locallang_db.php:tx_extendedshop_orders.shipping_tracking",		
			"config" => Array (
				"type" => "input",	
				"size" => "15",	
				"max" => "50",	
				"eval" => "trim",
			)
		),
	),
	"types" => Array (
		"0" => Array("showitem" => "hidden;;1;;1-1-1, code, customer, shippingcustomer, date, shipping;;2;;2-2-2, payment;;3;;3-3-3, total;;4;;4-4-4, weight, volume, trackingcode, state, ip, note, status, deliverydate")
	),
	"palettes" => Array (
		"1" => Array("showitem" => ""),
		"2" => Array("showitem" => "shipping_cost, shipping_tracking"),
		"3" => Array("showitem" => "payment_cost"),
		"4" => Array("showitem" => "total_notax"),
	)
);



$TCA["tx_extendedshop_rows"] = Array (
	"ctrl" => $TCA["tx_extendedshop_rows"]["ctrl"],
	"interface" => Array (
		"showRecordFieldList" => "hidden,ordercode,productcode,quantity,price,weight,volume,state,accessoriescodes,options"
	),
	"feInterface" => $TCA["tx_extendedshop_rows"]["feInterface"],
	"columns" => Array (
		"hidden" => Array (		
			"exclude" => 1,
			"label" => "LLL:EXT:lang/locallang_general.php:LGL.hidden",
			"config" => Array (
				"type" => "check",
				"default" => "0"
			)
		),
		"ordercode" => Array (		
			"exclude" => 1,		
			"label" => "LLL:EXT:extendedshop/locallang_db.php:tx_extendedshop_rows.ordercode",		
			"config" => Array (
				"type" => "group",	
				"internal_type" => "db",	
				"allowed" => "tx_extendedshop_orders",	
				"size" => 1,	
				"minitems" => 0,
				"maxitems" => 1,
			)
		),
		"productcode" => Array (		
			"exclude" => 1,		
			"label" => "LLL:EXT:extendedshop/locallang_db.php:tx_extendedshop_rows.productcode",		
			"config" => Array (
				"type" => "group",	
				"internal_type" => "db",	
				"allowed" => "tx_extendedshop_products",	
				"size" => 1,	
				"minitems" => 0,
				"maxitems" => 1,
			)
		),
		"quantity" => Array (		
			"exclude" => 1,		
			"label" => "LLL:EXT:extendedshop/locallang_db.php:tx_extendedshop_rows.quantity",		
			"config" => Array (
				"type" => "input",
				"size" => "4",
				"max" => "4",
				"eval" => "int",
				"checkbox" => "0",
				"range" => Array (
					"upper" => "1000",
					"lower" => "10"
				),
				"default" => 0
			)
		),
		"price" => Array (		
			"exclude" => 1,		
			"label" => "LLL:EXT:extendedshop/locallang_db.php:tx_extendedshop_rows.price",		
			"config" => Array (
				"type" => "input",	
				"size" => "5",	
				"eval" => "required",
			)
		),
		"weight" => Array (		
			"exclude" => 1,		
			"label" => "LLL:EXT:extendedshop/locallang_db.php:tx_extendedshop_rows.weight",		
			"config" => Array (
				"type" => "input",	
				"size" => "5",
			)
		),
		"volume" => Array (		
			"exclude" => 1,		
			"label" => "LLL:EXT:extendedshop/locallang_db.php:tx_extendedshop_rows.volume",		
			"config" => Array (
				"type" => "input",	
				"size" => "5",
			)
		),
		"state" => Array (		
			"exclude" => 1,		
			"label" => "LLL:EXT:extendedshop/locallang_db.php:tx_extendedshop_rows.state",		
			"config" => Array (
				"type" => "input",	
				"size" => "15",	
				"eval" => "trim",
			)
		),
		"itemcode" => Array (		
			"exclude" => 1,		
			"label" => "LLL:EXT:extendedshop/locallang_db.php:tx_extendedshop_rows.itemcode",		
			"config" => Array (
				"type" => "input",	
				"size" => "15",	
				"max" => "100",	
				"eval" => "trim",
			)
		),
		"options" => Array (		
			"exclude" => 1,		
			"label" => "LLL:EXT:extendedshop/locallang_db.php:tx_extendedshop_rows.options",		
			"config" => Array (
				"type" => "input",	
				"size" => "30",
			)
		),
	),
	"types" => Array (
		"0" => Array("showitem" => "hidden;;1;;1-1-1, ordercode, productcode, quantity, price, weight, volume, state, accessoriescodes, options")
	),
	"palettes" => Array (
		"1" => Array("showitem" => "")
	)
);



$TCA["tx_extendedshop_products"] = Array (
	"ctrl" => $TCA["tx_extendedshop_products"]["ctrl"],
	"interface" => Array (
		"showRecordFieldList" => "hidden,starttime,endtime,fe_group,code,title,summary,description,image,price,instock,www,ordered,weight,volume,correlatedaccessories,offertprice,discount,sizes,colors,correlatedproducts,documents,doc_labels,correlatedpage,pricedirect,supplier,thumbtype,tx_toicategory_toi_category,vat,max_for_order"
	),
	"feInterface" => $TCA["tx_extendedshop_products"]["feInterface"],
	"columns" => Array (
		"hidden" => Array (		
			"exclude" => 1,
			"label" => "LLL:EXT:lang/locallang_general.php:LGL.hidden",
			"config" => Array (
				"type" => "check",
				"default" => "0"
			)
		),
		"starttime" => Array (		
			"exclude" => 1,
			"label" => "LLL:EXT:lang/locallang_general.php:LGL.starttime",
			"config" => Array (
				"type" => "input",
				"size" => "8",
				"max" => "20",
				"eval" => "date",
				"default" => "0",
				"checkbox" => "0"
			)
		),
		"endtime" => Array (		
			"exclude" => 1,
			"label" => "LLL:EXT:lang/locallang_general.php:LGL.endtime",
			"config" => Array (
				"type" => "input",
				"size" => "8",
				"max" => "20",
				"eval" => "date",
				"checkbox" => "0",
				"default" => "0",
				"range" => Array (
					"upper" => mktime(0,0,0,12,31,2020),
					"lower" => mktime(0,0,0,date("m")-1,date("d"),date("Y"))
				)
			)
		),
		"code" => Array (		
			"exclude" => 1,		
			"label" => "LLL:EXT:extendedshop/locallang_db.php:tx_extendedshop_products.code",		
			"config" => Array (
				"type" => "input",	
				"size" => "15",	
				"max" => "100",	
				"eval" => "required,trim",
			)
		),
		"title" => Array (		
			"exclude" => 1,		
			"label" => "LLL:EXT:extendedshop/locallang_db.php:tx_extendedshop_products.title",		
			"config" => Array (
				"type" => "input",	
				"size" => "30",	
				"max" => "100",	
				"eval" => "required,trim",
			)
		),
		"pagetitle" => Array (		
			"exclude" => 1,		
			"label" => "LLL:EXT:extendedshop/locallang_db.php:tx_extendedshop_products.pagetitle",		
			"config" => Array (
				"type" => "input",	
				"size" => "30",	
				"max" => "100",	
				"eval" => "trim",
			)
		),
		"summary" => Array (		
			"exclude" => 1,		
			"label" => "LLL:EXT:extendedshop/locallang_db.php:tx_extendedshop_products.summary",		
			"config" => Array (
				"type" => "text",
				"cols" => "30",	
				"rows" => "3",
			)
		),
		"description" => Array (		
			"exclude" => 1,		
			"label" => "LLL:EXT:extendedshop/locallang_db.php:tx_extendedshop_products.description",		
			"config" => Array (
                "type" => "text",
                "cols" => "30",
                "rows" => "5",
                "wizards" => Array(
                    "_PADDING" => 2,
                    "RTE" => Array(
                        "notNewRecords" => 1,
                        "RTEonly" => 1,
                        "type" => "script",
                        "title" => "Full screen Rich Text Editing|Titolo",
                        "icon" => "wizard_rte2.gif",
                        "script" => "wizard_rte.php",
                    ),
                ),
            )
		),
		"image" => Array (		
			"exclude" => 1,		
			"label" => "LLL:EXT:extendedshop/locallang_db.php:tx_extendedshop_products.image",		
			"config" => Array (
				"type" => "group",
				"internal_type" => "file",
				"allowed" => $GLOBALS["TYPO3_CONF_VARS"]["GFX"]["imagefile_ext"],	
				"max_size" => 500,	
				"uploadfolder" => "uploads/tx_extendedshop",
				"show_thumbs" => 1,	
				"size" => 3,	
				"minitems" => 0,
				"maxitems" => 10,
			)
		),
		"price" => Array (		
			"exclude" => 1,		
			"label" => "LLL:EXT:extendedshop/locallang_db.php:tx_extendedshop_products.price",		
			"config" => Array (
				"type" => "input",	
				"size" => "5",
			)
		),
		"instock" => Array (		
			"exclude" => 1,		
			"label" => "LLL:EXT:extendedshop/locallang_db.php:tx_extendedshop_products.instock",		
			"config" => Array (
				"type" => "input",
				"size" => "4",
				"max" => "4",
				"eval" => "int",
				"checkbox" => "0",
				"range" => Array (
					"upper" => "1000000",
					"lower" => "-999"
				),
				"default" => 0
			)
		),
		"www" => Array (		
			"exclude" => 1,		
			"label" => "LLL:EXT:extendedshop/locallang_db.php:tx_extendedshop_products.www",		
			"config" => Array (
				"type" => "input",	
				"size" => "15",	
				"max" => "150",	
				"eval" => "trim",
			)
		),
		"ordered" => Array (		
			"exclude" => 1,		
			"label" => "LLL:EXT:extendedshop/locallang_db.php:tx_extendedshop_products.ordered",		
			"config" => Array (
				"type" => "none",
			)
		),
		"weight" => Array (		
			"exclude" => 1,		
			"label" => "LLL:EXT:extendedshop/locallang_db.php:tx_extendedshop_products.weight",		
			"config" => Array (
				"type" => "input",	
				"size" => "5",
			)
		),
		"volume" => Array (		
			"exclude" => 1,		
			"label" => "LLL:EXT:extendedshop/locallang_db.php:tx_extendedshop_products.volume",		
			"config" => Array (
				"type" => "input",	
				"size" => "5",
			)
		),
		"offertprice" => Array (		
			"exclude" => 1,		
			"label" => "LLL:EXT:extendedshop/locallang_db.php:tx_extendedshop_products.offertprice",		
			"config" => Array (
				"type" => "input",	
				"size" => "5",
			)
		),
		"discount" => Array (		
			"exclude" => 1,		
			"label" => "LLL:EXT:extendedshop/locallang_db.php:tx_extendedshop_products.discount",		
			"config" => Array (
				"type" => "input",	
				"size" => "5",	
				"max" => "2",	
				"eval" => "trim",
			)
		),
		"sizes" => Array (		
			"exclude" => 1,		
			"label" => "LLL:EXT:extendedshop/locallang_db.php:tx_extendedshop_products.sizes",		
			"config" => Array (
				"type" => "input",	
				"size" => "15",	
				"max" => "245",	
				"eval" => "trim",
			)
		),
		"colors" => Array (		
			"exclude" => 1,		
			"label" => "LLL:EXT:extendedshop/locallang_db.php:tx_extendedshop_products.colors",		
			"config" => Array (
				"type" => "input",	
				"size" => "15",	
				"max" => "245",	
				"eval" => "trim",
			)
		),
		"correlatedproducts" => Array (		
			"exclude" => 1,		
			"label" => "LLL:EXT:extendedshop/locallang_db.php:tx_extendedshop_products.correlatedproducts",		
			"config" => Array (
				"type" => "group",	
				"internal_type" => "db",	
				"allowed" => "tx_extendedshop_products",	
				"size" => 3,	
				"minitems" => 0,
				"maxitems" => 100,
			)
		),
		'sys_language_uid' => Array (
			'exclude' => 1,
			'label' => 'LLL:EXT:extendedshop/locallang_db.php:tx_extendedshop_products.lingua',
			'config' => Array (
				'type' => 'select',
				'foreign_table' => 'sys_language',
				'foreign_table_where' => 'ORDER BY sys_language.title',
				'items' => Array(
					Array('LLL:EXT:lang/locallang_general.php:LGL.allLanguages',-1),
					Array('LLL:EXT:lang/locallang_general.php:LGL.default_value',0)
				)
			)
		),
		'l18n_parent' => Array (
			'displayCond' => 'FIELD:sys_language_uid:>:0',
			'exclude' => 1,
			'label' => 'LLL:EXT:extendedshop/locallang_db.php:tx_extendedshop_products.parent',
			'config' => Array (
				'type' => 'select',
				'items' => Array (
					Array('', 0),
				),
				'foreign_table' => 'tx_extendedshop_pi1',
				'foreign_table_where' => 'AND tx_extendedshop_products.uid=###REC_FIELD_l18n_parent### AND tx_extendedshop_products.sys_language_uid IN (-1,0)',
			)
		),
		'l18n_diffsource' => Array(
			'config'=>array(
				'type'=>'passthrough')
		),
	    "documents" => Array (        
	        "exclude" => 1,        
	        "label" => "LLL:EXT:extendedshop/locallang_db.xml:tx_extendedshop_products.documents",        
	        "config" => Array (
            "type" => "group",
            "internal_type" => "file",
            "allowed" => $GLOBALS["TYPO3_CONF_VARS"]["GFX"]["imagefile_ext"],    
            "max_size" => 500,    
            "uploadfolder" => "uploads/tx_extendedshop",
            "size" => 10,    
            "minitems" => 0,
            "maxitems" => 10,
       		)
   		),
   		"doc_labels" => Array (
   			"exclude" => 1,		
			"label" => "LLL:EXT:extendedshop/locallang_db.php:tx_extendedshop_products.doc_labels",		
			"config" => Array (
				"type" => "text",
				"cols" => "30",	
				"rows" => "3",
			)
   		),
	    "correlatedpage" => Array (        
	        "exclude" => 1,        
	        "label" => "LLL:EXT:extendedshop/locallang_db.xml:tx_extendedshop_products.correlatedpage",        
	        "config" => Array (
	            "type" => "group",    
	            "internal_type" => "db",    
	            "allowed" => "pages",    
	            "size" => 10,    
	            "minitems" => 0,
	            "maxitems" => 10,
	        )
	    ),
	    "pricedirect" => Array (        
	        "exclude" => 1,        
	        "label" => "LLL:EXT:extendedshop/locallang_db.xml:tx_extendedshop_products.pricedirect",        
	        "config" => Array (
	            "type" => "input",    
	            "size" => "10",    
	            "max" => "10",
	        )
	    ),
	    "supplier" => Array (        
	        "exclude" => 1,        
	        "label" => "LLL:EXT:extendedshop/locallang_db.xml:tx_extendedshop_products.supplier",        
			"config" => Array (
	            "type" => "group",    
	            "internal_type" => "db",    
	            "allowed" => "fe_users",    
	            "size" => 1,    
	            "minitems" => 0,
	            "maxitems" => 1,
	        )
	    ),

	    "thumbtype" => Array (        
	        "exclude" => 1,        
	        "label" => "LLL:EXT:extendedshop/locallang_db.xml:tx_extendedshop_products.thumbtype",        
	        "config" => Array (
	            "type" => "input",    
	            "size" => "48",    
	            "max" => "255",
	        )
	    ),
	    "tx_toicategory_toi_category" => Array (		
	      	 "exclude" => 1,
		     "label" => "LLL:EXT:extendedshop/locallang_db.xml:tx_extendedshop_products.tx_toicategory_toi_category",
	   		 "config" => Array (
      			 "type" => "group",	
		      	 "internal_type" => "db",	
      			 "allowed" => "pages",	
		      	 "size" => 5,	
      			 "minitems" => 0,
		    	 "maxitems" => 100,
			 )
		 ),
		 'fe_group' => array(		
			'exclude' => 1,
			'label' => 'LLL:EXT:lang/locallang_general.xml:LGL.fe_group',
			'config' => array(
				'type' => 'select',
				'items' => array(
					array('', 0),
					array('LLL:EXT:lang/locallang_general.xml:LGL.hide_at_login', -1),
					array('LLL:EXT:lang/locallang_general.xml:LGL.any_login', -2),
					array('LLL:EXT:lang/locallang_general.xml:LGL.usergroups', '--div--')
				),
				'foreign_table' => 'fe_groups'
			)
		),
		"vat" => Array (        
            "exclude" => 1,        
            "label" => "LLL:EXT:extendedshop/locallang_db.xml:tx_extendedshop_products.vat",        
            "config" => Array (
                "type" => "select",    
                "items" => Array (
                    Array("",0),
                ),
                "foreign_table" => "tx_extendedshop_vat",    
                "foreign_table_where" => "ORDER BY tx_extendedshop_vat.uid",    
                "size" => 1,    
                "minitems" => 0,
                "maxitems" => 1,
            )
        ),
        "max_for_order" => Array (		
			"exclude" => 1,		
			"label" => "LLL:EXT:extendedshop/locallang_db.php:tx_extendedshop_products.max_for_order",		
			"config" => Array (
				"type" => "input",
				"size" => "4",
				"max" => "4",
				"eval" => "int",
				"range" => Array (
					"upper" => "1000",
					"lower" => "0"
				),
				"default" => 0
			)
		),
    ),
	"types" => Array (
		"0" => Array("showitem" => "hidden;;1;;1-1-1, sys_language_uid, I18n_parent, code, title;;2;;2-2-2, summary;;;;3-3-3, description;;;richtext[cut|copy|paste|formatblock|textcolor|bold|italic|underline|left|center|right|orderedlist|unorderedlist|outdent|indent|link|table|image|line|chMode]:rte_transform[mode=ts_css|imgpath=uploads/tx_extendedshop/rte/], image, price;;4;;1-1-1, instock, www, ordered, weight, volume, offertprice;;3;;1-1-1, sizes, colors, correlatedproducts, documents, doc_labels, correlatedpage, pricedirect, supplier, thumbtype, tx_toicategory_toi_category, max_for_order")
	),
	"palettes" => Array (
		"1" => Array("showitem" => "starttime, endtime, fe_group"),
		"2" => Array("showitem" => "pagetitle"),
		"3" => Array("showitem" => "offertpricenotax, discount"),
		"4" => Array("showitem" => "vat"),
	)
);

$TCA["tx_extendedshop_comments"] = array (
    "ctrl" => $TCA["tx_extendedshop_comments"]["ctrl"],
    "interface" => array (
        "showRecordFieldList" => "hidden,orderid,datetime,userid,message"
    ),
    "feInterface" => $TCA["tx_extendedshop_comments"]["feInterface"],
    "columns" => array (
        'hidden' => array (        
            'exclude' => 1,
            'label'   => 'LLL:EXT:lang/locallang_general.xml:LGL.hidden',
            'config'  => array (
                'type'    => 'check',
                'default' => '0'
            )
        ),
        "orderid" => Array (        
            "exclude" => 1,        
            "label" => "LLL:EXT:extendedshop/locallang_db.xml:tx_extendedshop_comments.order",        
            "config" => Array (
                "type"     => "input",
                "size"     => "4",
                "max"      => "4",
                "eval"     => "int",
                "checkbox" => "0",
                "range"    => Array (
                    "upper" => "1000",
                    "lower" => "10"
                ),
                "default" => 0
            )
        ),
        "datetime" => Array (        
            "exclude" => 1,        
            "label" => "LLL:EXT:extendedshop/locallang_db.xml:tx_extendedshop_comments.datetime",        
            "config" => Array (
                "type"     => "input",
                "size"     => "12",
                "max"      => "20",
                "eval"     => "datetime",
                "checkbox" => "0",
                "default"  => "0"
            )
        ),
        "userid" => Array (        
            "exclude" => 1,        
            "label" => "LLL:EXT:extendedshop/locallang_db.xml:tx_extendedshop_comments.userid",        
            "exclude" => 1,        
	        "label" => "LLL:EXT:extendedshop/locallang_db.xml:tx_extendedshop_products.supplier",        
			"config" => Array (
	            "type" => "group",    
	            "internal_type" => "db",    
	            "allowed" => "fe_users",    
	            "size" => 1,    
	            "minitems" => 0,
	            "maxitems" => 1,
	        )
        ),
        "message" => Array (        
            "exclude" => 1,        
            "label" => "LLL:EXT:extendedshop/locallang_db.xml:tx_extendedshop_comments.message",        
            "config" => Array (
                "type" => "text",
                "cols" => "30",    
                "rows" => "5",
            )
        ),
    ),
    "types" => array (
        "0" => array("showitem" => "hidden;;1;;1-1-1, orderid, datetime, userid, message")
    ),
    "palettes" => array (
        "1" => array("showitem" => "")
    )
);


$TCA["tx_extendedshop_shipping"] = Array (
	"ctrl" => $TCA["tx_extendedshop_shipping"]["ctrl"],
	"interface" => Array (
		"showRecordFieldList" => "hidden,starttime,endtime,title,description,image,price,pricenotax"
	),
	"feInterface" => $TCA["tx_extendedshop_shipping"]["feInterface"],
	"columns" => Array (
		"hidden" => Array (		
			"exclude" => 1,
			"label" => "LLL:EXT:lang/locallang_general.php:LGL.hidden",
			"config" => Array (
				"type" => "check",
				"default" => "0"
			)
		),
		"starttime" => Array (		
			"exclude" => 1,
			"label" => "LLL:EXT:lang/locallang_general.php:LGL.starttime",
			"config" => Array (
				"type" => "input",
				"size" => "8",
				"max" => "20",
				"eval" => "date",
				"default" => "0",
				"checkbox" => "0"
			)
		),
		"endtime" => Array (		
			"exclude" => 1,
			"label" => "LLL:EXT:lang/locallang_general.php:LGL.endtime",
			"config" => Array (
				"type" => "input",
				"size" => "8",
				"max" => "20",
				"eval" => "date",
				"checkbox" => "0",
				"default" => "0",
				"range" => Array (
					"upper" => mktime(0,0,0,12,31,2020),
					"lower" => mktime(0,0,0,date("m")-1,date("d"),date("Y"))
				)
			)
		),
		"title" => Array (		
			"exclude" => 1,		
			"label" => "LLL:EXT:extendedshop/locallang_db.php:tx_extendedshop_shipping.title",		
			"config" => Array (
				"type" => "input",	
				"size" => "30",	
				"max" => "100",	
				"eval" => "required,trim",
			)
		),
		"description" => Array (		
			"exclude" => 1,		
			"label" => "LLL:EXT:extendedshop/locallang_db.php:tx_extendedshop_shipping.description",		
			"config" => Array (
				"type" => "text",
				"cols" => "30",	
				"rows" => "3",
			)
		),
		"image" => Array (		
			"exclude" => 1,		
			"label" => "LLL:EXT:extendedshop/locallang_db.php:tx_extendedshop_shipping.image",		
			"config" => Array (
				"type" => "group",
				"internal_type" => "file",
				"allowed" => $GLOBALS["TYPO3_CONF_VARS"]["GFX"]["imagefile_ext"],	
				"max_size" => 500,	
				"uploadfolder" => "uploads/tx_extendedshop",
				"show_thumbs" => 1,	
				"size" => 1,	
				"minitems" => 0,
				"maxitems" => 1,
			)
		),
		"price" => Array (		
			"exclude" => 1,		
			"label" => "LLL:EXT:extendedshop/locallang_db.php:tx_extendedshop_shipping.price",		
			"config" => Array (
				"type" => "input",	
				"size" => "5",
			)
		),
		"pricenotax" => Array (		
			"exclude" => 1,		
			"label" => "LLL:EXT:extendedshop/locallang_db.php:tx_extendedshop_shipping.pricenotax",		
			"config" => Array (
				"type" => "input",	
				"size" => "5",
			)
		),
    ),
	"types" => Array (
		"0" => Array("showitem" => "hidden;;1;;1-1-1, title, description, image, price;;2;;1-1-1")
	),
	"palettes" => Array (
		"1" => Array("showitem" => "starttime, endtime"),
		"2" => Array("showitem" => "pricenotax"),
	)
);

$TCA["tx_extendedshop_shippingplace"] = Array (
	"ctrl" => $TCA["tx_extendedshop_shippingplace"]["ctrl"],
	"interface" => Array (
		"showRecordFieldList" => "hidden,starttime,endtime,country,shipping"
	),
	"feInterface" => $TCA["tx_extendedshop_shippingplace"]["feInterface"],
	"columns" => Array (
		"hidden" => Array (		
			"exclude" => 1,
			"label" => "LLL:EXT:lang/locallang_general.php:LGL.hidden",
			"config" => Array (
				"type" => "check",
				"default" => "0"
			)
		),
		"starttime" => Array (		
			"exclude" => 1,
			"label" => "LLL:EXT:lang/locallang_general.php:LGL.starttime",
			"config" => Array (
				"type" => "input",
				"size" => "8",
				"max" => "20",
				"eval" => "date",
				"default" => "0",
				"checkbox" => "0"
			)
		),
		"endtime" => Array (		
			"exclude" => 1,
			"label" => "LLL:EXT:lang/locallang_general.php:LGL.endtime",
			"config" => Array (
				"type" => "input",
				"size" => "8",
				"max" => "20",
				"eval" => "date",
				"checkbox" => "0",
				"default" => "0",
				"range" => Array (
					"upper" => mktime(0,0,0,12,31,2020),
					"lower" => mktime(0,0,0,date("m")-1,date("d"),date("Y"))
				)
			)
		),
		"country" => Array (		
			"exclude" => 1,		
			"label" => "LLL:EXT:extendedshop/locallang_db.php:tx_extendedshop_shippingplace.country",
			"displayCond" => "EXT:static_info_tables:LOADED:true",		
			"config" => Array (
				"type" => "select",	
				"items" => Array (
					Array('',0),
				),
				"foreign_table" => "static_countries",
				"foreign_table_where" => "AND static_countries.pid=0 ORDER BY static_countries.cn_short_en",
				"size" => 1,
				"minitems" => 0,
				"maxitems" => 1,
			)
		),
		"shipping" => Array (		
			"exclude" => 1,		
			"label" => "LLL:EXT:extendedshop/locallang_db.xml:tx_extendedshop_shippingplace.shipping",		
			"config" => Array (
				"type" => "inline",	
				"foreign_table" => "tx_extendedshop_shipping",
				"maxitems" => "50",
				"appearance" => Array (
					"useSortable" => 1,
				),
			)
		),
    ),
	"types" => Array (
		"0" => Array("showitem" => "hidden;;1;;1-1-1, title, country, shipping")
	),
	"palettes" => Array (
		"1" => Array("showitem" => "starttime, endtime"),
	)
);


$TCA["tx_extendedshop_vat"] = array (
    "ctrl" => $TCA["tx_extendedshop_vat"]["ctrl"],
    "interface" => array (
        "showRecordFieldList" => "hidden,name,percent"
    ),
    "feInterface" => $TCA["tx_extendedshop_vat"]["feInterface"],
    "columns" => array (
        'hidden' => array (        
            'exclude' => 1,
            'label'   => 'LLL:EXT:lang/locallang_general.xml:LGL.hidden',
            'config'  => array (
                'type'    => 'check',
                'default' => '0'
            )
        ),
        "name" => Array (        
            "exclude" => 1,        
            "label" => "LLL:EXT:extendedshop/locallang_db.xml:tx_extendedshop_vat.name",        
            "config" => Array (
                "type" => "input",    
                "size" => "30",    
                "max" => "50",    
                "eval" => "required",
            )
        ),
        "percent" => Array (        
            "exclude" => 1,        
            "label" => "LLL:EXT:extendedshop/locallang_db.xml:tx_extendedshop_vat.percent",        
            "config" => Array (
                "type" => "input",    
                "size" => "5",    
                "max" => "5",    
                "eval" => "required,double2",
            )
        ),
    ),
    "types" => array (
        "0" => array("showitem" => "hidden;;1;;1-1-1, name, percent")
    ),
    "palettes" => array (
        "1" => array("showitem" => "")
    )
);

?>
