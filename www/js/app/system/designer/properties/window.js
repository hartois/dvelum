/**
 * Properties panel for Window object
 */
Ext.define('designer.properties.Window',{
	extend:'designer.properties.Panel',
	
	initComponent:function()
	{
		if(this.tbar === undefined){
			this.tbar = [];
		}
		
		this.tbar.push({
        	 text:desLang.showWindow,
        	 scope:this,
        	 handler:this.showWindow
		});
		
		this.callParent();	
	},

	showWindow:function(){
		app.designer.switchView(0);
		app.designer.sendCommand({command:'showWindow',params:{name:this.objectName}});
	}
});