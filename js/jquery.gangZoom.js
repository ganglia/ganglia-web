(function ($) {
  $.fn.gangZoom = function (options) {
    var opts = $.extend({}, $.fn.gangZoom.defaults, options);

    if (! $.fn.gangZoom.styleAdded && opts.defaultStyle) {
      $("<style type='text/css'>" + opts.defaultStyle + "</style>").appendTo("head");
      $.fn.gangZoom.styleAdded = true;
    }
    
    return this.each(function() {
      var self = this;
      self.img = $(self);
      self.mouseDown = false;

      self.getSelection = function () {
        var graphWidth = self.img.width() - (opts.paddingLeft + opts.paddingRight);
        var selWidth = self.float.width();
        var selStart = self.float.position().left - self.img.offset().left - opts.paddingLeft;
        var selEnd = selStart + selWidth;

        return {selStart: selStart / graphWidth,
                selEnd: selEnd / graphWidth};
      };

      self.setMouseDown = function (down) {
        self.mouseDown = down;
        if (down) {
          $(document.body).css("cursor", "crosshair");
        } else {
          $(document.body).css("cursor", "default");
        }
      };
      
      self.cancel = function () {
        self.setMouseDown(false);
        var box = self.getSelection();
	$("#" + opts.floatId).remove();
        opts.cancel(box.selStart, box.selEnd);
      };

      self.go = function () {
        self.setMouseDown(false);
        var box = self.getSelection();
	$("#" + opts.floatId).remove();
        opts.done(box.selStart, box.selEnd);
      };

      self.updateOverlay = function (evt) {
        var curX = evt.pageX;
        
        if (self.startX > curX) {
          if (curX < self.minWidth) {
            curX = self.minWidth;
          }
          self.float.css({ left: curX });
        } else if (curX > self.maxWidth) {
          curX = self.maxWidth;
        }

        self.float.width(Math.abs(curX - self.startX));
      };

      $(document.body).mouseup(function (event) {
        if (self.mouseDown) {
          if (event.target == self.img || event.target == self.float[0]) {
            self.go();
          }
        }
      }).mousemove(function (event) {
        if (self.mouseDown) {
          if (event.target == self.float[0]) {
            self.updateOverlay(event);
          }
        }
      }).keyup(function (event) {
        if (event.keyCode == 27 && self.mouseDown) {
          self.cancel();
        }
      });
      
      self.img.mousedown(function (event) {
        event.preventDefault();
        self.shouldStopClick = false;
        self.stopped = false;
        var evt = event;

        setTimeout(function () {
          if (self.stopped) {
            return;
          }
          evt.stopPropagation();
          var clickX = evt.pageX;
          var clickY = evt.pageY;
          self.startX = clickX;

          $("#" + opts.floatId).remove();
          
          self.minWidth = self.img.offset().left + opts.paddingLeft;
          self.maxWidth = self.img.offset().left + (self.img.width() - opts.paddingRight);
          if ((clickX > self.maxWidth) || (clickX < self.minWidth)) {
            return;
          }

          self.shouldStopClick = true;
          self.setMouseDown(true);

          var float = $("<div id='" + opts.floatId + "'>").css({
            position: "absolute", left: clickX,
            top: self.img.offset().top, zIndex: 1000,
            height: self.img.height()}).
              width(10).
              mousemove(function(evt) { return true; }).
              mouseup(function() { return true; }).
              keyup(function() { return true; }).
              appendTo(document.body);

          self.float = float;

        }, opts.clickTimeout);
      }).mousemove(function (evt) {
        if (self.mouseDown && self.float) {
          self.updateOverlay(evt);
        }
      }).mouseup(function (evt) {
        if (self.mouseDown) {
          self.go();
        } else {
          self.cancel();
        }
      }).click(function (event) {
        if (self.shouldStopClick) {
          event.preventDefault();
        } else {
          self.stopped = true;
        }
      });
    });
  };

  $.fn.gangZoom.defStyle = "#gangZoomFloater {";
  $.fn.gangZoom.defStyle += "border: 1px solid black;";
  $.fn.gangZoom.defStyle += "background: white;";
  $.fn.gangZoom.defStyle += "opacity: 0.7;";
  $.fn.gangZoom.defStyle += "position: absolute;";
  $.fn.gangZoom.defStyle += "top: 0;";
  $.fn.gangZoom.defStyle += "height: 100%;";

  $.fn.gangZoom.defaults = {
    clickTimeout: 500,
    floatId: 'gangZoomFloater',
    defaultStyle: $.fn.gangZoom.defStyle,
    paddingLeft: 20,
    paddingRight: 20,
    paddingTop: 20,
    paddingBottom: 40,
    done: function (selStart, selEnd) {},
    cancel: function (selStart, selEnd) {}
  };

  $.fn.gangZoom.styleAdded = false;
})(jQuery);
