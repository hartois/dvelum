Ext.define('designer.iconSelectorWindow',{
	extend:'Ext.Window',
	layout:'border',
	dataTree:null,
	dataView:null,
	viewPanel:null,
	controllerUrl:'',
	listAction:'imgdirlist',
	imagesAction:'imglist',
	width:500,
	height:300,
	modal:true,

	iconWidth:16,
	iconHeight:16,


	initComponent:function(){

		this.dataTree = Ext.create('app.FilesystemTree',{
			controllerUrl:this.controllerUrl,
			listAction:this.listAction,
			region:'west',
			minWidth:250,
			width:250,
			collapsible:true,
			listeners:{
				'select':{
						fn:function(RowModel, record, index, eOpts ){
							var store = this.dataView.getStore();
							store.proxy.setExtraParam('dir' , record.get('id'));
							store.load();
						},
						scope:this
				}
			}
		});


		ImageModel = Ext.define('ImageModel', {
		        extend: 'Ext.data.Model',
		        fields: [
		           {name: 'name'},
		           {name: 'url'},
		           {name: 'path'}
		        ]
		});

		this.dataView =  Ext.create('Ext.view.View', {
            store: Ext.create('Ext.data.Store', {
                model: 'ImageModel',
                proxy: {
                    type: 'ajax',
                    url: this.controllerUrl + this.imagesAction,
                    reader: {
                        type: 'json',
                        root: 'data'
                    },
            		autoLoad:false
                }
            }),
            tpl: [
                '<tpl for=".">',
                    '<div class="thumb-wrap" id="{name}">',
                    '<div class="thumb" align="center"><img src="{url}" title="{name}" width="'+this.iconWidth+'" height="'+this.iconHeight+'"></div>',
                    '<span class="x-editable">{shortName}</span></div>',
                '</tpl>',
                '<div class="x-clear"></div>'
            ],
            multiSelect: false,
            height: 310,
            trackOver: true,
            cls:'images-view',
            overItemCls: 'x-item-over',
            itemSelector: 'div.thumb-wrap',
            emptyText: desLang.noImagesToDisplay,
            prepareData: function(data) {
                Ext.apply(data, {
                    shortName: Ext.util.Format.ellipsis(data.name, 15)
                });
                return data;
            },
            listeners: {
                selectionchange: function(dv, nodes ){
                    var l = nodes.length,
                        s = l !== 1 ? 's' : '';
                    this.up('panel').setTitle('Simple DataView (' + l + ' item' + s + ' selected)');
                }
            }
        });

		this.viewPanel = Ext.create('Ext.Panel',{
			region:'center',
			items:[this.dataView],
			frame: false,
			bodyCls:'formBody',
			autoScroll:true
		});

		this.items = [this.dataTree , this.viewPanel];

		this.buttons = [
		      {
		    	  text:desLang.select,
		    	  scope:this,
		    	  handler:this.onSelect
		      },{
		    	  text:desLang.cancel,
		    	  scope:this,
		    	  handler:this.close
		      }
		]

	    this.callParent();

		this.addEvents(
	            /**
	             * @event select
	             * Fires when action is selected
	             * @param string url
	             */
	            'select'
		);
	},
	onSelect:function()
	{
		var sm = this.dataView.getSelectionModel();
		if(!sm.hasSelection()){
			Ext.Msg.alert(appLang.MESSAGE, desLang.selectIcon);
			return;
		}
		this.fireEvent('select',sm.getLastSelected().get('path'));
		this.close();
	}
});