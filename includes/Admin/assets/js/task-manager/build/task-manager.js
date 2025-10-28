/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
/******/ 	var __webpack_modules__ = ({

/***/ "./components/Header.tsx":
/*!*******************************!*\
  !*** ./components/Header.tsx ***!
  \*******************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ Header)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _context_ViewContext__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ../context/ViewContext */ "./context/ViewContext.tsx");


function Header() {
    const { view, setView } = (0,_context_ViewContext__WEBPACK_IMPORTED_MODULE_1__.useView)();
    return (react__WEBPACK_IMPORTED_MODULE_0___default().createElement("div", { className: "header" },
        react__WEBPACK_IMPORTED_MODULE_0___default().createElement("nav", { className: "views-filter" },
            react__WEBPACK_IMPORTED_MODULE_0___default().createElement("ul", null, _context_ViewContext__WEBPACK_IMPORTED_MODULE_1__.AVAILABLE_VIEWS.map((availableView) => (react__WEBPACK_IMPORTED_MODULE_0___default().createElement("li", { key: availableView, className: view === availableView ? 'active' : '', onClick: () => setView(availableView) }, window.WPO_AOM_TaskManager.views[availableView])))))));
}


/***/ }),

/***/ "./components/Page.tsx":
/*!*****************************!*\
  !*** ./components/Page.tsx ***!
  \*****************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ Page)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _context_ViewContext__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ../context/ViewContext */ "./context/ViewContext.tsx");
/* harmony import */ var _Header__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./Header */ "./components/Header.tsx");
/* harmony import */ var _views_Calendar_calendar__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ../views/Calendar/calendar */ "./views/Calendar/calendar.tsx");
/* harmony import */ var _views_Kanban_KanbanView__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ../views/Kanban/KanbanView */ "./views/Kanban/KanbanView.tsx");





function Page() {
    const { view } = (0,_context_ViewContext__WEBPACK_IMPORTED_MODULE_1__.useView)();
    return (react__WEBPACK_IMPORTED_MODULE_0__.createElement("div", { className: "inner" },
        react__WEBPACK_IMPORTED_MODULE_0__.createElement(_Header__WEBPACK_IMPORTED_MODULE_2__["default"], null),
        react__WEBPACK_IMPORTED_MODULE_0__.createElement("div", { className: `view ${view}-view` },
            view === 'kanban' && react__WEBPACK_IMPORTED_MODULE_0__.createElement(_views_Kanban_KanbanView__WEBPACK_IMPORTED_MODULE_4__.KanbanView, null),
            view === 'calendar' && react__WEBPACK_IMPORTED_MODULE_0__.createElement(_views_Calendar_calendar__WEBPACK_IMPORTED_MODULE_3__["default"], null))));
}


/***/ }),

/***/ "./context/TaskContext.tsx":
/*!*********************************!*\
  !*** ./context/TaskContext.tsx ***!
  \*********************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   TaskProvider: () => (/* binding */ TaskProvider),
/* harmony export */   useTasks: () => (/* binding */ useTasks)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _utils_api__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ../utils/api */ "./utils/api.ts");
var __awaiter = (undefined && undefined.__awaiter) || function (thisArg, _arguments, P, generator) {
    function adopt(value) { return value instanceof P ? value : new P(function (resolve) { resolve(value); }); }
    return new (P || (P = Promise))(function (resolve, reject) {
        function fulfilled(value) { try { step(generator.next(value)); } catch (e) { reject(e); } }
        function rejected(value) { try { step(generator["throw"](value)); } catch (e) { reject(e); } }
        function step(result) { result.done ? resolve(result.value) : adopt(result.value).then(fulfilled, rejected); }
        step((generator = generator.apply(thisArg, _arguments || [])).next());
    });
};


const TaskContext = (0,react__WEBPACK_IMPORTED_MODULE_0__.createContext)(undefined);
const TaskProvider = ({ children }) => {
    const [tasks, setTasks] = (0,react__WEBPACK_IMPORTED_MODULE_0__.useState)([]);
    const [statuses, setStatuses] = (0,react__WEBPACK_IMPORTED_MODULE_0__.useState)([]);
    const loadTasks = () => __awaiter(void 0, void 0, void 0, function* () {
        try {
            const data = yield (0,_utils_api__WEBPACK_IMPORTED_MODULE_1__.fetchTasks)();
            setTasks(data);
        }
        catch (error) {
            console.error("Failed to fetch tasks:", error);
        }
    });
    const saveTask = (taskId, updates) => __awaiter(void 0, void 0, void 0, function* () {
        // try {
        // 	const updated = await updateTask(taskId, updates);
        // 	setTasks(prev =>
        // 		prev.map(t => (t.id === taskId ? { ...t, ...updated } : t))
        // 	);
        // } catch (err) {
        // 	console.error("Failed to update task:", err);
        // }
    });
    const loadStatuses = () => __awaiter(void 0, void 0, void 0, function* () {
        try {
            const data = yield (0,_utils_api__WEBPACK_IMPORTED_MODULE_1__.fetchStatus)();
            setStatuses(data);
        }
        catch (error) {
            console.error("Failed to fetch columns:", error);
        }
    });
    return (react__WEBPACK_IMPORTED_MODULE_0___default().createElement(TaskContext.Provider, { value: {
            tasks,
            setTasks,
            loadTasks,
            saveTask,
            statuses,
            loadStatuses,
        } }, children));
};
const useTasks = () => {
    const context = (0,react__WEBPACK_IMPORTED_MODULE_0__.useContext)(TaskContext);
    if (!context) {
        throw new Error('useTasks must be used within a TaskProvider');
    }
    return context;
};


/***/ }),

/***/ "./context/ViewContext.tsx":
/*!*********************************!*\
  !*** ./context/ViewContext.tsx ***!
  \*********************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   AVAILABLE_VIEWS: () => (/* binding */ AVAILABLE_VIEWS),
/* harmony export */   ViewProvider: () => (/* binding */ ViewProvider),
/* harmony export */   useView: () => (/* binding */ useView)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);

// Define all available views.
const AVAILABLE_VIEWS = ['kanban', 'calendar'];
const ViewContext = (0,react__WEBPACK_IMPORTED_MODULE_0__.createContext)(undefined);
/**
 * Provider component to wrap the part of the app that needs access to the view state.
 *
 * @param children
 * @constructor
 */
const ViewProvider = ({ children }) => {
    const [view, setView] = (0,react__WEBPACK_IMPORTED_MODULE_0__.useState)('kanban');
    return (react__WEBPACK_IMPORTED_MODULE_0___default().createElement(ViewContext.Provider, { value: { view, setView } }, children));
};
/**
 * Custom hook to use the ViewContext.
 *
 * @returns {ViewContextType} The current view and a function to set the view
 * @throws Will throw an error if used outside a ViewProvider
 */
const useView = () => {
    const context = (0,react__WEBPACK_IMPORTED_MODULE_0__.useContext)(ViewContext);
    if (!context) {
        throw new Error('useView must be used within a ViewProvider');
    }
    return context;
};


/***/ }),

/***/ "./node_modules/@atlaskit/pragmatic-drag-and-drop-auto-scroll/dist/esm/entry-point/element.js":
/*!****************************************************************************************************!*\
  !*** ./node_modules/@atlaskit/pragmatic-drag-and-drop-auto-scroll/dist/esm/entry-point/element.js ***!
  \****************************************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   autoScrollForElements: () => (/* binding */ autoScrollForElements),
/* harmony export */   autoScrollWindowForElements: () => (/* binding */ autoScrollWindowForElements)
/* harmony export */ });
/* harmony import */ var _atlaskit_pragmatic_drag_and_drop_element_adapter__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @atlaskit/pragmatic-drag-and-drop/element/adapter */ "./node_modules/@atlaskit/pragmatic-drag-and-drop/dist/esm/entry-point/element/adapter.js");
/* harmony import */ var _over_element_make_api__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ../over-element/make-api */ "./node_modules/@atlaskit/pragmatic-drag-and-drop-auto-scroll/dist/esm/over-element/make-api.js");


var api = (0,_over_element_make_api__WEBPACK_IMPORTED_MODULE_1__.makeApi)({
  monitor: _atlaskit_pragmatic_drag_and_drop_element_adapter__WEBPACK_IMPORTED_MODULE_0__.monitorForElements
});
var autoScrollForElements = api.autoScroll;
var autoScrollWindowForElements = api.autoScrollWindow;

/***/ }),

/***/ "./node_modules/@atlaskit/pragmatic-drag-and-drop-auto-scroll/dist/esm/over-element/data-attributes.js":
/*!*************************************************************************************************************!*\
  !*** ./node_modules/@atlaskit/pragmatic-drag-and-drop-auto-scroll/dist/esm/over-element/data-attributes.js ***!
  \*************************************************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   addScrollableAttribute: () => (/* binding */ addScrollableAttribute),
/* harmony export */   dataAttribute: () => (/* binding */ dataAttribute),
/* harmony export */   selector: () => (/* binding */ selector)
/* harmony export */ });
var dataAttribute = 'data-auto-scrollable';
var selector = "[".concat(dataAttribute, "=\"true\"]");
function addScrollableAttribute(element) {
  element.setAttribute(dataAttribute, 'true');
  return function () {
    return element.removeAttribute(dataAttribute);
  };
}

/***/ }),

/***/ "./node_modules/@atlaskit/pragmatic-drag-and-drop-auto-scroll/dist/esm/over-element/get-scroll-by.js":
/*!***********************************************************************************************************!*\
  !*** ./node_modules/@atlaskit/pragmatic-drag-and-drop-auto-scroll/dist/esm/over-element/get-scroll-by.js ***!
  \***********************************************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   getScrollBy: () => (/* binding */ getScrollBy)
/* harmony export */ });
/* harmony import */ var _shared_can_scroll_on_edge__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ../shared/can-scroll-on-edge */ "./node_modules/@atlaskit/pragmatic-drag-and-drop-auto-scroll/dist/esm/shared/can-scroll-on-edge.js");
/* harmony import */ var _shared_edges__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ../shared/edges */ "./node_modules/@atlaskit/pragmatic-drag-and-drop-auto-scroll/dist/esm/shared/edges.js");
/* harmony import */ var _shared_get_over_element_hitbox__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ../shared/get-over-element-hitbox */ "./node_modules/@atlaskit/pragmatic-drag-and-drop-auto-scroll/dist/esm/shared/get-over-element-hitbox.js");
/* harmony import */ var _shared_get_scroll_change__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ../shared/get-scroll-change */ "./node_modules/@atlaskit/pragmatic-drag-and-drop-auto-scroll/dist/esm/shared/get-scroll-change.js");
/* harmony import */ var _shared_is_axis_allowed__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ../shared/is-axis-allowed */ "./node_modules/@atlaskit/pragmatic-drag-and-drop-auto-scroll/dist/esm/shared/is-axis-allowed.js");
/* harmony import */ var _shared_is_within__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ../shared/is-within */ "./node_modules/@atlaskit/pragmatic-drag-and-drop-auto-scroll/dist/esm/shared/is-within.js");






function getRectDefault(element) {
  return element.getBoundingClientRect();
}
function getScrollBy(_ref) {
  var element = _ref.element,
    input = _ref.input,
    timeSinceLastFrame = _ref.timeSinceLastFrame,
    engagement = _ref.engagement,
    config = _ref.config,
    allowedAxis = _ref.allowedAxis,
    _ref$getRect = _ref.getRect,
    getRect = _ref$getRect === void 0 ? getRectDefault : _ref$getRect;
  var client = {
    x: input.clientX,
    y: input.clientY
  };
  var clientRect = getRect(element);
  var scrollableEdges = _shared_edges__WEBPACK_IMPORTED_MODULE_1__.edges.reduce(function (map, edge) {
    var hitbox = _shared_get_over_element_hitbox__WEBPACK_IMPORTED_MODULE_2__.getOverElementHitbox[edge]({
      clientRect: clientRect,
      config: config
    });
    var axis = _shared_edges__WEBPACK_IMPORTED_MODULE_1__.edgeAxisLookup[edge];

    // Note: changing the allowed axis during a drag will not
    // reset time dampening. It was decided it would be too
    // complex to implement initially, and we can add it
    // later if needed.
    if (!(0,_shared_is_axis_allowed__WEBPACK_IMPORTED_MODULE_4__.isAxisAllowed)(axis, allowedAxis)) {
      return map;
    }
    if (!(0,_shared_is_within__WEBPACK_IMPORTED_MODULE_5__.isWithin)({
      client: client,
      clientRect: hitbox
    })) {
      return map;
    }
    if (!_shared_can_scroll_on_edge__WEBPACK_IMPORTED_MODULE_0__.canScrollOnEdge[edge](element)) {
      return map;
    }
    map.set(edge, {
      edge: edge,
      hitbox: hitbox
    });
    return map;
  }, new Map());
  var left = function () {
    var axis = 'horizontal';
    var leftEdge = scrollableEdges.get('left');
    if (leftEdge) {
      return (0,_shared_get_scroll_change__WEBPACK_IMPORTED_MODULE_3__.getScrollChange)({
        client: client,
        edge: leftEdge.edge,
        hitbox: leftEdge.hitbox,
        axis: axis,
        timeSinceLastFrame: timeSinceLastFrame,
        engagement: engagement,
        isDistanceDampeningEnabled: true,
        config: config
      });
    }
    var rightEdge = scrollableEdges.get('right');
    if (rightEdge) {
      return (0,_shared_get_scroll_change__WEBPACK_IMPORTED_MODULE_3__.getScrollChange)({
        client: client,
        edge: rightEdge.edge,
        hitbox: rightEdge.hitbox,
        axis: axis,
        timeSinceLastFrame: timeSinceLastFrame,
        engagement: engagement,
        isDistanceDampeningEnabled: true,
        config: config
      });
    }
    return 0;
  }();
  var top = function () {
    var axis = 'vertical';
    var bottomEdge = scrollableEdges.get('bottom');
    if (bottomEdge) {
      return (0,_shared_get_scroll_change__WEBPACK_IMPORTED_MODULE_3__.getScrollChange)({
        client: client,
        edge: bottomEdge.edge,
        hitbox: bottomEdge.hitbox,
        axis: axis,
        timeSinceLastFrame: timeSinceLastFrame,
        engagement: engagement,
        isDistanceDampeningEnabled: true,
        config: config
      });
    }
    var topEdge = scrollableEdges.get('top');
    if (topEdge) {
      return (0,_shared_get_scroll_change__WEBPACK_IMPORTED_MODULE_3__.getScrollChange)({
        client: client,
        edge: topEdge.edge,
        hitbox: topEdge.hitbox,
        axis: axis,
        timeSinceLastFrame: timeSinceLastFrame,
        engagement: engagement,
        isDistanceDampeningEnabled: true,
        config: config
      });
    }
    return 0;
  }();
  return {
    left: left,
    top: top
  };
}

/***/ }),

/***/ "./node_modules/@atlaskit/pragmatic-drag-and-drop-auto-scroll/dist/esm/over-element/make-api.js":
/*!******************************************************************************************************!*\
  !*** ./node_modules/@atlaskit/pragmatic-drag-and-drop-auto-scroll/dist/esm/over-element/make-api.js ***!
  \******************************************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   makeApi: () => (/* binding */ makeApi)
/* harmony export */ });
/* harmony import */ var _babel_runtime_helpers_defineProperty__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @babel/runtime/helpers/defineProperty */ "./node_modules/@babel/runtime/helpers/esm/defineProperty.js");
/* harmony import */ var _atlaskit_pragmatic_drag_and_drop_combine__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @atlaskit/pragmatic-drag-and-drop/combine */ "./node_modules/@atlaskit/pragmatic-drag-and-drop/dist/esm/entry-point/combine.js");
/* harmony import */ var _atlaskit_pragmatic_drag_and_drop_once__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @atlaskit/pragmatic-drag-and-drop/once */ "./node_modules/@atlaskit/pragmatic-drag-and-drop/dist/esm/entry-point/once.js");
/* harmony import */ var _shared_scheduler__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ../shared/scheduler */ "./node_modules/@atlaskit/pragmatic-drag-and-drop-auto-scroll/dist/esm/shared/scheduler.js");
/* harmony import */ var _data_attributes__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ./data-attributes */ "./node_modules/@atlaskit/pragmatic-drag-and-drop-auto-scroll/dist/esm/over-element/data-attributes.js");
/* harmony import */ var _try_scroll__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ./try-scroll */ "./node_modules/@atlaskit/pragmatic-drag-and-drop-auto-scroll/dist/esm/over-element/try-scroll.js");

function ownKeys(e, r) { var t = Object.keys(e); if (Object.getOwnPropertySymbols) { var o = Object.getOwnPropertySymbols(e); r && (o = o.filter(function (r) { return Object.getOwnPropertyDescriptor(e, r).enumerable; })), t.push.apply(t, o); } return t; }
function _objectSpread(e) { for (var r = 1; r < arguments.length; r++) { var t = null != arguments[r] ? arguments[r] : {}; r % 2 ? ownKeys(Object(t), !0).forEach(function (r) { (0,_babel_runtime_helpers_defineProperty__WEBPACK_IMPORTED_MODULE_0__["default"])(e, r, t[r]); }) : Object.getOwnPropertyDescriptors ? Object.defineProperties(e, Object.getOwnPropertyDescriptors(t)) : ownKeys(Object(t)).forEach(function (r) { Object.defineProperty(e, r, Object.getOwnPropertyDescriptor(t, r)); }); } return e; }





function makeApi(_ref) {
  var monitor = _ref.monitor;
  var elementRegistry = new Map();
  var windowRegistry = new Set();
  function autoScroll(args) {
    // Warn during development if trying to add auto scroll to an element
    // that is not scrollable.
    // Note: this can produce a false positive when a scroll container is not
    // scrollable initially, but becomes scrollable during a drag.
    // I thought of adding the warning as I think it would be a more common pitfall
    // to accidentally register auto scrolling on the wrong element
    // If requested, we could provide a mechanism to opt out of this warning
    if (true) {
      var _window$getComputedSt = window.getComputedStyle(args.element),
        overflowX = _window$getComputedSt.overflowX,
        overflowY = _window$getComputedSt.overflowY;
      var isScrollable = overflowX === 'auto' || overflowX === 'scroll' || overflowY === 'auto' || overflowY === 'scroll';
      if (!isScrollable) {
        // eslint-disable-next-line no-console
        console.warn('Auto scrolling has been attached to an element that appears not to be scrollable', {
          element: args.element,
          overflowX: overflowX,
          overflowY: overflowY
        });
      }
    }

    // Warn if there is an existing registration
    if (true) {
      var existing = elementRegistry.get(args.element);
      if (existing) {
        // eslint-disable-next-line no-console
        console.warn('You have already registered autoScrolling on the same element', {
          existing: existing,
          proposed: args
        });
      }
    }
    elementRegistry.set(args.element, args);
    var cleanup = (0,_atlaskit_pragmatic_drag_and_drop_combine__WEBPACK_IMPORTED_MODULE_1__.combine)((0,_data_attributes__WEBPACK_IMPORTED_MODULE_4__.addScrollableAttribute)(args.element), function () {
      return elementRegistry.delete(args.element);
    });
    return (0,_atlaskit_pragmatic_drag_and_drop_once__WEBPACK_IMPORTED_MODULE_2__.once)(cleanup);
  }
  function autoScrollWindow() {
    var args = arguments.length > 0 && arguments[0] !== undefined ? arguments[0] : {};
    // Putting `args` in a unique object so that
    // each call will create a unique entry, even if a consumer
    // shares the `args` object between calls.
    // Just being safe here.
    var unique = _objectSpread({}, args);
    windowRegistry.add(unique);
    function cleanup() {
      windowRegistry.delete(unique);
    }
    return (0,_atlaskit_pragmatic_drag_and_drop_once__WEBPACK_IMPORTED_MODULE_2__.once)(cleanup);
  }
  function findEntry(element) {
    var _elementRegistry$get;
    return (_elementRegistry$get = elementRegistry.get(element)) !== null && _elementRegistry$get !== void 0 ? _elementRegistry$get : null;
  }
  function getWindowScrollEntries() {
    return Array.from(windowRegistry);
  }
  function onFrame(_ref2) {
    var latestArgs = _ref2.latestArgs,
      underUsersPointer = _ref2.underUsersPointer,
      timeSinceLastFrame = _ref2.timeSinceLastFrame;
    (0,_try_scroll__WEBPACK_IMPORTED_MODULE_5__.tryScroll)({
      input: latestArgs.location.current.input,
      source: latestArgs.source,
      findEntry: findEntry,
      underUsersPointer: underUsersPointer,
      timeSinceLastFrame: timeSinceLastFrame,
      getWindowScrollEntries: getWindowScrollEntries
    });
  }
  (0,_shared_scheduler__WEBPACK_IMPORTED_MODULE_3__.getScheduler)(monitor).onFrame(onFrame);
  return {
    autoScroll: autoScroll,
    autoScrollWindow: autoScrollWindow
  };
}

/***/ }),

/***/ "./node_modules/@atlaskit/pragmatic-drag-and-drop-auto-scroll/dist/esm/over-element/try-scroll.js":
/*!********************************************************************************************************!*\
  !*** ./node_modules/@atlaskit/pragmatic-drag-and-drop-auto-scroll/dist/esm/over-element/try-scroll.js ***!
  \********************************************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   tryScroll: () => (/* binding */ tryScroll)
/* harmony export */ });
/* harmony import */ var _shared_configuration__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ../shared/configuration */ "./node_modules/@atlaskit/pragmatic-drag-and-drop-auto-scroll/dist/esm/shared/configuration.js");
/* harmony import */ var _shared_engagement_history__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ../shared/engagement-history */ "./node_modules/@atlaskit/pragmatic-drag-and-drop-auto-scroll/dist/esm/shared/engagement-history.js");
/* harmony import */ var _data_attributes__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./data-attributes */ "./node_modules/@atlaskit/pragmatic-drag-and-drop-auto-scroll/dist/esm/over-element/data-attributes.js");
/* harmony import */ var _get_scroll_by__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ./get-scroll-by */ "./node_modules/@atlaskit/pragmatic-drag-and-drop-auto-scroll/dist/esm/over-element/get-scroll-by.js");
function _createForOfIteratorHelper(r, e) { var t = "undefined" != typeof Symbol && r[Symbol.iterator] || r["@@iterator"]; if (!t) { if (Array.isArray(r) || (t = _unsupportedIterableToArray(r)) || e && r && "number" == typeof r.length) { t && (r = t); var _n = 0, F = function F() {}; return { s: F, n: function n() { return _n >= r.length ? { done: !0 } : { done: !1, value: r[_n++] }; }, e: function e(r) { throw r; }, f: F }; } throw new TypeError("Invalid attempt to iterate non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method."); } var o, a = !0, u = !1; return { s: function s() { t = t.call(r); }, n: function n() { var r = t.next(); return a = r.done, r; }, e: function e(r) { u = !0, o = r; }, f: function f() { try { a || null == t.return || t.return(); } finally { if (u) throw o; } } }; }
function _unsupportedIterableToArray(r, a) { if (r) { if ("string" == typeof r) return _arrayLikeToArray(r, a); var t = {}.toString.call(r).slice(8, -1); return "Object" === t && r.constructor && (t = r.constructor.name), "Map" === t || "Set" === t ? Array.from(r) : "Arguments" === t || /^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(t) ? _arrayLikeToArray(r, a) : void 0; } }
function _arrayLikeToArray(r, a) { (null == a || a > r.length) && (a = r.length); for (var e = 0, n = Array(a); e < a; e++) n[e] = r[e]; return n; }




function isScrollingAvailable(value) {
  return Boolean(value.top || value.left);
}
function tryScrollElements(_ref) {
  var _container$getConfigu, _container$getAllowed, _container$getAllowed2;
  var target = _ref.target,
    input = _ref.input,
    source = _ref.source,
    findEntry = _ref.findEntry,
    timeSinceLastFrame = _ref.timeSinceLastFrame,
    _ref$available = _ref.available,
    available = _ref$available === void 0 ? {
      top: true,
      left: true
    } : _ref$available;
  // we cannot do any more scrolling
  if (!isScrollingAvailable(available)) {
    return available;
  }

  // run out of parents to search
  if (!target) {
    return available;
  }
  var element = target.closest(_data_attributes__WEBPACK_IMPORTED_MODULE_2__.selector);

  // cannot find any more scroll containers
  if (!element) {
    return available;
  }
  var container = findEntry(element);

  // cannot find registration, this is bad.
  // fail and just exit
  if (!container) {
    return available;
  }
  function continueSearchUp() {
    var _element$parentElemen;
    return tryScrollElements({
      target: (_element$parentElemen = element === null || element === void 0 ? void 0 : element.parentElement) !== null && _element$parentElemen !== void 0 ? _element$parentElemen : null,
      findEntry: findEntry,
      source: source,
      timeSinceLastFrame: timeSinceLastFrame,
      input: input,
      available: available
    });
  }
  var feedback = {
    input: input,
    source: source,
    element: element
  };

  // Engagement is not marked if scrolling is explicitly not allowed
  if (container.canScroll && !container.canScroll(feedback)) {
    return continueSearchUp();
  }

  // Marking engagement even if no edges are scrollable.
  // We are marking engagement as soon as the element is scrolled over
  var engagement = (0,_shared_engagement_history__WEBPACK_IMPORTED_MODULE_1__.markAndGetEngagement)(element);
  var config = (0,_shared_configuration__WEBPACK_IMPORTED_MODULE_0__.getInternalConfig)((_container$getConfigu = container.getConfiguration) === null || _container$getConfigu === void 0 ? void 0 : _container$getConfigu.call(container, feedback));
  var allowedAxis = (_container$getAllowed = (_container$getAllowed2 = container.getAllowedAxis) === null || _container$getAllowed2 === void 0 ? void 0 : _container$getAllowed2.call(container, feedback)) !== null && _container$getAllowed !== void 0 ? _container$getAllowed : 'all';
  var scrollBy = (0,_get_scroll_by__WEBPACK_IMPORTED_MODULE_3__.getScrollBy)({
    element: element,
    engagement: engagement,
    input: input,
    timeSinceLastFrame: timeSinceLastFrame,
    allowedAxis: allowedAxis,
    config: config
  });

  // Only allow scrolling in directions that have not already been used
  var scroll = {
    top: 0,
    left: 0
  };
  if (available.top && scrollBy.top !== 0) {
    scroll.top = scrollBy.top;
    // can no longer scroll on the top after this
    available.top = false;
  }
  if (available.left && scrollBy.left !== 0) {
    scroll.left = scrollBy.left;
    // can no longer scroll on the left after this
    available.left = false;
  }

  // Only scroll if there is something to scroll
  if (scroll.top !== 0 || scroll.left !== 0) {
    element.scrollBy(scroll);
  }
  return continueSearchUp();
}
function tryScrollWindow(_ref2) {
  var input = _ref2.input,
    timeSinceLastFrame = _ref2.timeSinceLastFrame,
    available = _ref2.available,
    source = _ref2.source,
    entries = _ref2.entries;
  var element = document.documentElement;
  var feedback = {
    input: input,
    source: source,
    element: element
  };
  var _iterator = _createForOfIteratorHelper(entries),
    _step;
  try {
    for (_iterator.s(); !(_step = _iterator.n()).done;) {
      var _entry$getConfigurati, _entry$getAllowedAxis, _entry$getAllowedAxis2;
      var entry = _step.value;
      // this entry is not allowing scrolling, we need to look for another
      if (entry.canScroll && !entry.canScroll(feedback)) {
        continue;
      }

      // Note: if we had an event for when the user is leaving a tab
      // we _could_ conceptually reset the engagement
      var engagement = (0,_shared_engagement_history__WEBPACK_IMPORTED_MODULE_1__.markAndGetEngagement)(element);
      var config = (0,_shared_configuration__WEBPACK_IMPORTED_MODULE_0__.getInternalConfig)((_entry$getConfigurati = entry.getConfiguration) === null || _entry$getConfigurati === void 0 ? void 0 : _entry$getConfigurati.call(entry, feedback));
      var allowedAxis = (_entry$getAllowedAxis = (_entry$getAllowedAxis2 = entry.getAllowedAxis) === null || _entry$getAllowedAxis2 === void 0 ? void 0 : _entry$getAllowedAxis2.call(entry, feedback)) !== null && _entry$getAllowedAxis !== void 0 ? _entry$getAllowedAxis : 'all';
      var scrollBy = (0,_get_scroll_by__WEBPACK_IMPORTED_MODULE_3__.getScrollBy)({
        element: element,
        engagement: engagement,
        input: input,
        config: config,
        allowedAxis: allowedAxis,
        getRect: function getRect(element) {
          return DOMRect.fromRect({
            y: 0,
            x: 0,
            width: element.clientWidth,
            height: element.clientHeight
          });
        },
        timeSinceLastFrame: timeSinceLastFrame
      });
      var scroll = {
        top: available.top ? scrollBy.top : 0,
        left: available.left ? scrollBy.left : 0
      };

      // only trigger a scroll if we are actually scrolling
      if (scroll.top !== 0 || scroll.left !== 0) {
        element.scrollBy(scroll);
      }

      // We only want the window to scroll once
      break;
    }
  } catch (err) {
    _iterator.e(err);
  } finally {
    _iterator.f();
  }
}
function tryScroll(_ref3) {
  var input = _ref3.input,
    findEntry = _ref3.findEntry,
    timeSinceLastFrame = _ref3.timeSinceLastFrame,
    source = _ref3.source,
    getWindowScrollEntries = _ref3.getWindowScrollEntries,
    underUsersPointer = _ref3.underUsersPointer;
  // We are matching browser behaviour and scrolling inner elements
  // before outer ones. So we try to scroll scroller containers before
  // the window.
  var remainder = tryScrollElements({
    target: underUsersPointer,
    timeSinceLastFrame: timeSinceLastFrame,
    input: input,
    source: source,
    findEntry: findEntry
  });

  // Check if we can do any window scrolling
  if (!isScrollingAvailable(remainder)) {
    return;
  }
  tryScrollWindow({
    input: input,
    source: source,
    entries: getWindowScrollEntries(),
    timeSinceLastFrame: timeSinceLastFrame,
    available: remainder
  });
}

/***/ }),

/***/ "./node_modules/@atlaskit/pragmatic-drag-and-drop-auto-scroll/dist/esm/shared/axis.js":
/*!********************************************************************************************!*\
  !*** ./node_modules/@atlaskit/pragmatic-drag-and-drop-auto-scroll/dist/esm/shared/axis.js ***!
  \********************************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   axisLookup: () => (/* binding */ axisLookup)
/* harmony export */ });
var vertical = {
  start: 'top',
  end: 'bottom',
  point: 'y',
  size: 'height'
};
var horizontal = {
  start: 'left',
  end: 'right',
  point: 'x',
  size: 'width'
};
var axisLookup = {
  vertical: {
    mainAxis: vertical,
    crossAxis: horizontal
  },
  horizontal: {
    mainAxis: horizontal,
    crossAxis: vertical
  }
};

/***/ }),

/***/ "./node_modules/@atlaskit/pragmatic-drag-and-drop-auto-scroll/dist/esm/shared/can-scroll-on-edge.js":
/*!**********************************************************************************************************!*\
  !*** ./node_modules/@atlaskit/pragmatic-drag-and-drop-auto-scroll/dist/esm/shared/can-scroll-on-edge.js ***!
  \**********************************************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   canScrollOnEdge: () => (/* binding */ canScrollOnEdge)
/* harmony export */ });
var canScrollOnEdge = {
  // Notes:
  //
  // 🌏 Chrome 115.0: uses fractional units for `scrollLeft` and `scrollTop`
  //    (and fractional units don't reach true integer maximum when zoomed in / out)
  // 🍎 Safari 16.5.2: no fractional units
  // 🦊 Firefox 115.0: no fractional units

  // we have some scroll we can move backwards into
  top: function top(element) {
    return element.scrollTop > 0;
  },
  // We have some scroll we can move forward into
  right: function right(element) {
    return Math.ceil(element.scrollLeft) + element.clientWidth < element.scrollWidth;
  },
  // We have some scroll we can move forwards into
  bottom: function bottom(element) {
    return Math.ceil(element.scrollTop) + element.clientHeight < element.scrollHeight;
  },
  // we have some scroll we can move back into.
  left: function left(element) {
    return element.scrollLeft > 0;
  }
};

/***/ }),

/***/ "./node_modules/@atlaskit/pragmatic-drag-and-drop-auto-scroll/dist/esm/shared/configuration.js":
/*!*****************************************************************************************************!*\
  !*** ./node_modules/@atlaskit/pragmatic-drag-and-drop-auto-scroll/dist/esm/shared/configuration.js ***!
  \*****************************************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   getInternalConfig: () => (/* binding */ getInternalConfig)
/* harmony export */ });
/* harmony import */ var _babel_runtime_helpers_defineProperty__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @babel/runtime/helpers/defineProperty */ "./node_modules/@babel/runtime/helpers/esm/defineProperty.js");

function ownKeys(e, r) { var t = Object.keys(e); if (Object.getOwnPropertySymbols) { var o = Object.getOwnPropertySymbols(e); r && (o = o.filter(function (r) { return Object.getOwnPropertyDescriptor(e, r).enumerable; })), t.push.apply(t, o); } return t; }
function _objectSpread(e) { for (var r = 1; r < arguments.length; r++) { var t = null != arguments[r] ? arguments[r] : {}; r % 2 ? ownKeys(Object(t), !0).forEach(function (r) { (0,_babel_runtime_helpers_defineProperty__WEBPACK_IMPORTED_MODULE_0__["default"])(e, r, t[r]); }) : Object.getOwnPropertyDescriptors ? Object.defineProperties(e, Object.getOwnPropertyDescriptors(t)) : ownKeys(Object(t)).forEach(function (r) { Object.defineProperty(e, r, Object.getOwnPropertyDescriptor(t, r)); }); } return e; }
var baseConfig = {
  startHitboxAtPercentageRemainingOfElement: {
    top: 0.25,
    right: 0.25,
    bottom: 0.25,
    left: 0.25
  },
  maxScrollAtPercentageRemainingOfHitbox: {
    top: 0.5,
    right: 0.5,
    bottom: 0.5,
    left: 0.5
  },
  timeDampeningDurationMs: 400,
  // Too big and it's too easy to trigger auto scrolling
  // Too small and it's too hard 😅
  maxMainAxisHitboxSize: 180
};

/** What the max scroll should be per second. Using "per second" rather than "per frame"
 * as we want a consistent scroll speed regardless of frame rate.
 *
 *
 * I explored trying to make the max scroll speed dynamic based on particular factors.
 * However, it ended up being difficult to find a _perfect_ formula.
 *
 * Likely the perfect answer would involve:
 * - the size of the scrollable element
 * - the size of the scrollable element relative to the screen size
 * - the size of the drag preview
 * - the size of elements being scrolled in scrollable elements (expensive and difficult to compute)
 */
var maxPixelScrollPerSecond = {
  // What the value would be if we were scrolling at 15px per frame at 60fps.
  // This is the default as it works well for most experiences.
  // In certain scenarios though it can feel a bit slow.
  standard: 15 * 60,
  // What the value would be if we were scrolling at 25px per frame at 60fps.
  // This is not the default as it feels too fast for a lot of experiences.
  fast: 25 * 60
};
function getInternalConfig(provided) {
  var _provided$maxScrollSp;
  return _objectSpread(_objectSpread({}, baseConfig), {}, {
    // only allowing limited control over the config at this stage
    maxPixelScrollPerSecond: maxPixelScrollPerSecond[(_provided$maxScrollSp = provided === null || provided === void 0 ? void 0 : provided.maxScrollSpeed) !== null && _provided$maxScrollSp !== void 0 ? _provided$maxScrollSp : 'standard']
  });
}

/***/ }),

/***/ "./node_modules/@atlaskit/pragmatic-drag-and-drop-auto-scroll/dist/esm/shared/edges.js":
/*!*********************************************************************************************!*\
  !*** ./node_modules/@atlaskit/pragmatic-drag-and-drop-auto-scroll/dist/esm/shared/edges.js ***!
  \*********************************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   edgeAxisLookup: () => (/* binding */ edgeAxisLookup),
/* harmony export */   edges: () => (/* binding */ edges)
/* harmony export */ });
var edges = ['top', 'right', 'bottom', 'left'];
var edgeAxisLookup = {
  top: 'vertical',
  right: 'horizontal',
  bottom: 'vertical',
  left: 'horizontal'
};

/***/ }),

/***/ "./node_modules/@atlaskit/pragmatic-drag-and-drop-auto-scroll/dist/esm/shared/engagement-history.js":
/*!**********************************************************************************************************!*\
  !*** ./node_modules/@atlaskit/pragmatic-drag-and-drop-auto-scroll/dist/esm/shared/engagement-history.js ***!
  \**********************************************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   clearEngagementHistory: () => (/* binding */ clearEngagementHistory),
/* harmony export */   clearUnusedEngagements: () => (/* binding */ clearUnusedEngagements),
/* harmony export */   markAndGetEngagement: () => (/* binding */ markAndGetEngagement),
/* harmony export */   markEngagement: () => (/* binding */ markEngagement)
/* harmony export */ });
var ledger = new Map();
var requested = new Set();
function markAndGetEngagement(element) {
  markEngagement(element);
  var entry = ledger.get(element);
  if (entry) {
    return entry;
  }
  var fresh = {
    timeOfEngagementStart: Date.now()
  };
  ledger.set(element, fresh);
  return fresh;
}
function markEngagement(element) {
  requested.add(element);
}
function clearUnusedEngagements(fn) {
  // make sure previous engagement requests don't linger
  requested.clear();

  // perform the required work
  fn();

  // if engagements where not requested, purge it
  ledger.forEach(function (_, element) {
    if (!requested.has(element)) {
      ledger.delete(element);
    }
  });

  // cleaning up after ourselves
  requested.clear();
}
function clearEngagementHistory() {
  ledger.clear();
}

/***/ }),

/***/ "./node_modules/@atlaskit/pragmatic-drag-and-drop-auto-scroll/dist/esm/shared/get-over-element-hitbox.js":
/*!***************************************************************************************************************!*\
  !*** ./node_modules/@atlaskit/pragmatic-drag-and-drop-auto-scroll/dist/esm/shared/get-over-element-hitbox.js ***!
  \***************************************************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   getOverElementHitbox: () => (/* binding */ getOverElementHitbox)
/* harmony export */ });
/* harmony import */ var _babel_runtime_helpers_defineProperty__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @babel/runtime/helpers/defineProperty */ "./node_modules/@babel/runtime/helpers/esm/defineProperty.js");
/* harmony import */ var _axis__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./axis */ "./node_modules/@atlaskit/pragmatic-drag-and-drop-auto-scroll/dist/esm/shared/axis.js");
/* harmony import */ var _side__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./side */ "./node_modules/@atlaskit/pragmatic-drag-and-drop-auto-scroll/dist/esm/shared/side.js");



function makeGetHitbox(_ref) {
  var edge = _ref.edge,
    axis = _ref.axis;
  return function hitbox(_ref2) {
    var clientRect = _ref2.clientRect,
      config = _ref2.config;
    var _axisLookup$axis = _axis__WEBPACK_IMPORTED_MODULE_1__.axisLookup[axis],
      mainAxis = _axisLookup$axis.mainAxis,
      crossAxis = _axisLookup$axis.crossAxis;
    var side = _side__WEBPACK_IMPORTED_MODULE_2__.mainAxisSideLookup[edge];
    var mainAxisHitboxSize = Math.min(
    // scale the size of the hitbox down for smaller elements
    config.startHitboxAtPercentageRemainingOfElement[edge] * clientRect[mainAxis.size],
    // Don't let the hitbox grow too big for big elements
    config.maxMainAxisHitboxSize);
    return DOMRect.fromRect((0,_babel_runtime_helpers_defineProperty__WEBPACK_IMPORTED_MODULE_0__["default"])((0,_babel_runtime_helpers_defineProperty__WEBPACK_IMPORTED_MODULE_0__["default"])((0,_babel_runtime_helpers_defineProperty__WEBPACK_IMPORTED_MODULE_0__["default"])((0,_babel_runtime_helpers_defineProperty__WEBPACK_IMPORTED_MODULE_0__["default"])({}, mainAxis.point, side === 'start' ?
    // begin from the start edge and grow inwards
    clientRect[mainAxis.point] :
    // begin from inside the end edge and grow towards the end edge
    clientRect[mainAxis.point] + clientRect[mainAxis.size] - mainAxisHitboxSize), crossAxis.point, clientRect[crossAxis.point]), mainAxis.size, mainAxisHitboxSize), crossAxis.size, clientRect[crossAxis.size]));
  };
}
var getOverElementHitbox = {
  top: makeGetHitbox({
    axis: 'vertical',
    edge: 'top'
  }),
  right: makeGetHitbox({
    axis: 'horizontal',
    edge: 'right'
  }),
  bottom: makeGetHitbox({
    axis: 'vertical',
    edge: 'bottom'
  }),
  left: makeGetHitbox({
    axis: 'horizontal',
    edge: 'left'
  })
};

/***/ }),

/***/ "./node_modules/@atlaskit/pragmatic-drag-and-drop-auto-scroll/dist/esm/shared/get-percentage-in-range.js":
/*!***************************************************************************************************************!*\
  !*** ./node_modules/@atlaskit/pragmatic-drag-and-drop-auto-scroll/dist/esm/shared/get-percentage-in-range.js ***!
  \***************************************************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   getPercentageInRange: () => (/* binding */ getPercentageInRange)
/* harmony export */ });
function getPercentageInRange(_ref) {
  var startOfRange = _ref.startOfRange,
    endOfRange = _ref.endOfRange,
    value = _ref.value;
  // checking inputs
  var isValid = startOfRange < endOfRange;
  if (!isValid) {
    return 0;
  }
  if (value < startOfRange) {
    return 0;
  }
  if (value > endOfRange) {
    return 1;
  }
  var range = endOfRange - startOfRange;
  return (value - startOfRange) / range;
}

/***/ }),

/***/ "./node_modules/@atlaskit/pragmatic-drag-and-drop-auto-scroll/dist/esm/shared/get-scroll-change.js":
/*!*********************************************************************************************************!*\
  !*** ./node_modules/@atlaskit/pragmatic-drag-and-drop-auto-scroll/dist/esm/shared/get-scroll-change.js ***!
  \*********************************************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   getScrollChange: () => (/* binding */ getScrollChange)
/* harmony export */ });
/* harmony import */ var _axis__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./axis */ "./node_modules/@atlaskit/pragmatic-drag-and-drop-auto-scroll/dist/esm/shared/axis.js");
/* harmony import */ var _get_percentage_in_range__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./get-percentage-in-range */ "./node_modules/@atlaskit/pragmatic-drag-and-drop-auto-scroll/dist/esm/shared/get-percentage-in-range.js");
/* harmony import */ var _side__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./side */ "./node_modules/@atlaskit/pragmatic-drag-and-drop-auto-scroll/dist/esm/shared/side.js");




// We want a consistent scroll speed across devices, regardless of framerate
function getMaxScrollChange(_ref) {
  var timeSinceLastFrame = _ref.timeSinceLastFrame,
    config = _ref.config;
  var targetScrollPerMs = config.maxPixelScrollPerSecond / 1000;

  // Adjusting out target scroll rate to match the frame rate of the target device
  // This will pull the scroll speed down on high frame rate devices
  // so we get a consistent visual scroll speed regardless of device.
  var proposed = Math.ceil(targetScrollPerMs * timeSinceLastFrame);

  // If lots of time as passed since that last frame (such on lower frame rate devices)
  // we don't want the scroll speed to be too fast, otherwise it can feel jumpy
  // We are capping the scroll speed at what it would be if we were hitting 60fps
  var maximum = config.maxPixelScrollPerSecond / 60;
  return Math.min(proposed, maximum);
}
function getDistanceDampening(_ref2) {
  var client = _ref2.client,
    axis = _ref2.axis,
    edge = _ref2.edge,
    hitbox = _ref2.hitbox,
    config = _ref2.config;
  var mainAxis = _axis__WEBPACK_IMPORTED_MODULE_0__.axisLookup[axis].mainAxis;
  var side = _side__WEBPACK_IMPORTED_MODULE_2__.mainAxisSideLookup[edge];

  // We want to hit the max speed before the edge of the hitbox
  var maxSpeedBuffer = hitbox[mainAxis.size] * config.maxScrollAtPercentageRemainingOfHitbox[edge];
  if (side === 'end') {
    return (0,_get_percentage_in_range__WEBPACK_IMPORTED_MODULE_1__.getPercentageInRange)({
      startOfRange: hitbox[mainAxis.start],
      endOfRange: hitbox[mainAxis.end] - maxSpeedBuffer,
      value: client[mainAxis.point]
    });
  }

  // Moving towards start edge

  var raw = (0,_get_percentage_in_range__WEBPACK_IMPORTED_MODULE_1__.getPercentageInRange)({
    startOfRange: hitbox[mainAxis.start] + maxSpeedBuffer,
    endOfRange: hitbox[mainAxis.end],
    value: client[mainAxis.point]
  });
  // When moving near start edge
  // - the 'end' edge is where we start scrolling
  // - the 'start' edge is where we reach max speed
  // So we need to invert the percentage when moving backwards
  return 1 - raw;
}
function getScrollChange(_ref3) {
  var client = _ref3.client,
    timeSinceLastFrame = _ref3.timeSinceLastFrame,
    engagement = _ref3.engagement,
    axis = _ref3.axis,
    hitbox = _ref3.hitbox,
    edge = _ref3.edge,
    isDistanceDampeningEnabled = _ref3.isDistanceDampeningEnabled,
    config = _ref3.config;
  // We have two forms of speed dampening:
  // 1. 🗺️ Distance
  // The closer you are to a hitbox edge, the faster the scroll speed will be
  // 2. ⏱️ Time
  // When first entering a scroll container we want to dampening all scrolling
  // This is to prevent super fast auto scrolling when first entering into
  // a scroll container, or when lifting in a scroll container

  var maxScroll = getMaxScrollChange({
    timeSinceLastFrame: timeSinceLastFrame,
    config: config
  });
  var percentageDistanceDampening = isDistanceDampeningEnabled ? getDistanceDampening({
    client: client,
    edge: edge,
    hitbox: hitbox,
    axis: axis,
    config: config
  }) : 1;

  // Dampen speed by time
  var percentageThroughTimeDampening = (0,_get_percentage_in_range__WEBPACK_IMPORTED_MODULE_1__.getPercentageInRange)({
    startOfRange: engagement.timeOfEngagementStart,
    endOfRange: engagement.timeOfEngagementStart + config.timeDampeningDurationMs,
    value: Date.now()
  });

  // Calculate how much of the max scroll we should apply based on dampening
  var percentageOfMaxScroll = percentageDistanceDampening * percentageThroughTimeDampening;

  // We _could_ ease this update (`Math.pow(percentageOfMaxSpeed, 2)`)
  // But linear is feeling really good
  // Always scrolling by at least one pixel, otherwise the scroll does nothing
  var scroll = Math.max(maxScroll * percentageOfMaxScroll, 1);
  var side = _side__WEBPACK_IMPORTED_MODULE_2__.mainAxisSideLookup[edge];

  // When moving backwards, we will be scrolling backwards
  return side === 'end' ? scroll : -1 * scroll;
}

/***/ }),

/***/ "./node_modules/@atlaskit/pragmatic-drag-and-drop-auto-scroll/dist/esm/shared/is-axis-allowed.js":
/*!*******************************************************************************************************!*\
  !*** ./node_modules/@atlaskit/pragmatic-drag-and-drop-auto-scroll/dist/esm/shared/is-axis-allowed.js ***!
  \*******************************************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   isAxisAllowed: () => (/* binding */ isAxisAllowed)
/* harmony export */ });
function isAxisAllowed(axis, allowedAxis) {
  return allowedAxis === 'all' || axis === allowedAxis;
}

/***/ }),

/***/ "./node_modules/@atlaskit/pragmatic-drag-and-drop-auto-scroll/dist/esm/shared/is-within.js":
/*!*************************************************************************************************!*\
  !*** ./node_modules/@atlaskit/pragmatic-drag-and-drop-auto-scroll/dist/esm/shared/is-within.js ***!
  \*************************************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   isWithin: () => (/* binding */ isWithin)
/* harmony export */ });
function isWithin(_ref) {
  var client = _ref.client,
    clientRect = _ref.clientRect;
  return (
    // is within horizontal bounds
    client.x >= clientRect.x && client.x <= clientRect.x + clientRect.width &&
    // is within vertical bounds
    client.y >= clientRect.y && client.y <= clientRect.y + clientRect.height
  );
}

/***/ }),

/***/ "./node_modules/@atlaskit/pragmatic-drag-and-drop-auto-scroll/dist/esm/shared/scheduler.js":
/*!*************************************************************************************************!*\
  !*** ./node_modules/@atlaskit/pragmatic-drag-and-drop-auto-scroll/dist/esm/shared/scheduler.js ***!
  \*************************************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   getScheduler: () => (/* binding */ getScheduler)
/* harmony export */ });
/* harmony import */ var _atlaskit_pragmatic_drag_and_drop_private_get_element_from_point_without_honey_pot__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @atlaskit/pragmatic-drag-and-drop/private/get-element-from-point-without-honey-pot */ "./node_modules/@atlaskit/pragmatic-drag-and-drop/dist/esm/entry-point/private/get-element-from-point-without-honey-pot.js");
/* harmony import */ var _engagement_history__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./engagement-history */ "./node_modules/@atlaskit/pragmatic-drag-and-drop-auto-scroll/dist/esm/shared/engagement-history.js");


// We keep this map so that "over element" scrolling and "overflow" scrolling
// can leverage the same scheduler.
// The 'monitor' is the key for looking up schedulers
var schedulers = new Map();
function getScheduler(monitor) {
  var scheduler = schedulers.get(monitor);
  if (scheduler) {
    // @ts-expect-error: I don't know how to link the DragType generic between the key and the value when the
    // monitor itself is the key
    return scheduler;
  }
  var created = makeScheduler(monitor);
  schedulers.set(monitor, created);
  return created;
}
function makeScheduler(monitor) {
  var state = {
    type: 'idle'
  };
  var callbacks = [];
  function loop(timeLastFrameFinished) {
    if (state.type !== 'running') {
      return;
    }
    var timeSinceLastFrame = timeLastFrameFinished - state.timeLastFrameFinished;
    var _state = state,
      latestArgs = _state.latestArgs;

    // A common starting lookup point for determining
    // which auto scroller should be used, and what should be scrolled.
    var underUsersPointer = (0,_atlaskit_pragmatic_drag_and_drop_private_get_element_from_point_without_honey_pot__WEBPACK_IMPORTED_MODULE_0__.getElementFromPointWithoutHoneypot)({
      x: latestArgs.location.current.input.clientX,
      y: latestArgs.location.current.input.clientY
    });
    (0,_engagement_history__WEBPACK_IMPORTED_MODULE_1__.clearUnusedEngagements)(function () {
      callbacks.forEach(function (onFrame) {
        return onFrame({
          underUsersPointer: underUsersPointer,
          latestArgs: latestArgs,
          timeSinceLastFrame: timeSinceLastFrame
        });
      });
    });
    state.timeLastFrameFinished = timeLastFrameFinished;
    state.frameId = requestAnimationFrame(loop);
  }
  function reset() {
    if (state.type === 'idle') {
      return;
    }
    cancelAnimationFrame(state.frameId);
    (0,_engagement_history__WEBPACK_IMPORTED_MODULE_1__.clearEngagementHistory)();
    state = {
      type: 'idle'
    };
  }
  function start(args) {
    if (state.type !== 'idle') {
      return;
    }
    state = {
      // Waiting a frame so we can accurately determine `timeSinceLastFrame`.
      type: 'initializing',
      latestArgs: args,
      frameId: requestAnimationFrame(function (timeLastFrameFinished) {
        if (state.type !== 'initializing') {
          return;
        }
        state = {
          type: 'running',
          timeLastFrameFinished: timeLastFrameFinished,
          latestArgs: state.latestArgs,
          frameId: requestAnimationFrame(loop)
        };
      })
    };
  }

  // this module might have been imported after a drag has started
  // We are starting the auto scroller if we get an update event and
  // the auto scroller has not started yet
  function update(args) {
    if (state.type === 'idle') {
      start(args);
      return;
    }
    state.latestArgs = args;
  }

  // Not exposing a way to stop listening
  monitor({
    onDragStart: start,
    onDropTargetChange: update,
    onDrag: update,
    onDrop: reset
  });
  var api = {
    onFrame: function onFrame(fn) {
      callbacks.push(fn);
    }
  };
  return api;
}

/***/ }),

/***/ "./node_modules/@atlaskit/pragmatic-drag-and-drop-auto-scroll/dist/esm/shared/side.js":
/*!********************************************************************************************!*\
  !*** ./node_modules/@atlaskit/pragmatic-drag-and-drop-auto-scroll/dist/esm/shared/side.js ***!
  \********************************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   mainAxisSideLookup: () => (/* binding */ mainAxisSideLookup)
/* harmony export */ });
var mainAxisSideLookup = {
  top: 'start',
  right: 'end',
  bottom: 'end',
  left: 'start'
};

/***/ }),

/***/ "./node_modules/@atlaskit/pragmatic-drag-and-drop-hitbox/dist/esm/closest-edge.js":
/*!****************************************************************************************!*\
  !*** ./node_modules/@atlaskit/pragmatic-drag-and-drop-hitbox/dist/esm/closest-edge.js ***!
  \****************************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   attachClosestEdge: () => (/* binding */ attachClosestEdge),
/* harmony export */   extractClosestEdge: () => (/* binding */ extractClosestEdge)
/* harmony export */ });
/* harmony import */ var _babel_runtime_helpers_defineProperty__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @babel/runtime/helpers/defineProperty */ "./node_modules/@babel/runtime/helpers/esm/defineProperty.js");

function ownKeys(e, r) { var t = Object.keys(e); if (Object.getOwnPropertySymbols) { var o = Object.getOwnPropertySymbols(e); r && (o = o.filter(function (r) { return Object.getOwnPropertyDescriptor(e, r).enumerable; })), t.push.apply(t, o); } return t; }
function _objectSpread(e) { for (var r = 1; r < arguments.length; r++) { var t = null != arguments[r] ? arguments[r] : {}; r % 2 ? ownKeys(Object(t), !0).forEach(function (r) { (0,_babel_runtime_helpers_defineProperty__WEBPACK_IMPORTED_MODULE_0__["default"])(e, r, t[r]); }) : Object.getOwnPropertyDescriptors ? Object.defineProperties(e, Object.getOwnPropertyDescriptors(t)) : ownKeys(Object(t)).forEach(function (r) { Object.defineProperty(e, r, Object.getOwnPropertyDescriptor(t, r)); }); } return e; }
// re-exporting type to make it easy to use

var getDistanceToEdge = {
  top: function top(rect, client) {
    return Math.abs(client.y - rect.top);
  },
  right: function right(rect, client) {
    return Math.abs(rect.right - client.x);
  },
  bottom: function bottom(rect, client) {
    return Math.abs(rect.bottom - client.y);
  },
  left: function left(rect, client) {
    return Math.abs(client.x - rect.left);
  }
};

// using a symbol so we can guarantee a key with a unique value
var uniqueKey = Symbol('closestEdge');

/**
 * Adds a unique `Symbol` to the `userData` object. Use with `extractClosestEdge()` for type safe lookups.
 */
function attachClosestEdge(userData, _ref) {
  var _entries$sort$0$edge, _entries$sort$;
  var element = _ref.element,
    input = _ref.input,
    allowedEdges = _ref.allowedEdges;
  var client = {
    x: input.clientX,
    y: input.clientY
  };
  // I tried caching the result of `getBoundingClientRect()` for a single
  // frame in order to improve performance.
  // However, on measurement I saw no improvement. So no longer caching
  var rect = element.getBoundingClientRect();
  var entries = allowedEdges.map(function (edge) {
    return {
      edge: edge,
      value: getDistanceToEdge[edge](rect, client)
    };
  });

  // edge can be `null` when `allowedEdges` is []
  var addClosestEdge = (_entries$sort$0$edge = (_entries$sort$ = entries.sort(function (a, b) {
    return a.value - b.value;
  })[0]) === null || _entries$sort$ === void 0 ? void 0 : _entries$sort$.edge) !== null && _entries$sort$0$edge !== void 0 ? _entries$sort$0$edge : null;
  return _objectSpread(_objectSpread({}, userData), {}, (0,_babel_runtime_helpers_defineProperty__WEBPACK_IMPORTED_MODULE_0__["default"])({}, uniqueKey, addClosestEdge));
}

/**
 * Returns the value added by `attachClosestEdge()` to the `userData` object. It will return `null` if there is no value.
 */
function extractClosestEdge(userData) {
  var _ref2;
  return (_ref2 = userData[uniqueKey]) !== null && _ref2 !== void 0 ? _ref2 : null;
}

/***/ }),

/***/ "./node_modules/@atlaskit/pragmatic-drag-and-drop/dist/esm/adapter/element-adapter-native-data-key.js":
/*!************************************************************************************************************!*\
  !*** ./node_modules/@atlaskit/pragmatic-drag-and-drop/dist/esm/adapter/element-adapter-native-data-key.js ***!
  \************************************************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   elementAdapterNativeDataKey: () => (/* binding */ elementAdapterNativeDataKey)
/* harmony export */ });
/**
 * This key has been pulled into a separate module
 * so that the external adapter does not need to import
 * the element adapter
 */
var elementAdapterNativeDataKey = 'application/vnd.pdnd';

/***/ }),

/***/ "./node_modules/@atlaskit/pragmatic-drag-and-drop/dist/esm/adapter/element-adapter.js":
/*!********************************************************************************************!*\
  !*** ./node_modules/@atlaskit/pragmatic-drag-and-drop/dist/esm/adapter/element-adapter.js ***!
  \********************************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   draggable: () => (/* binding */ draggable),
/* harmony export */   dropTargetForElements: () => (/* binding */ dropTargetForElements),
/* harmony export */   monitorForElements: () => (/* binding */ monitorForElements)
/* harmony export */ });
/* harmony import */ var _babel_runtime_helpers_slicedToArray__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @babel/runtime/helpers/slicedToArray */ "./node_modules/@babel/runtime/helpers/esm/slicedToArray.js");
/* harmony import */ var bind_event_listener__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! bind-event-listener */ "./node_modules/bind-event-listener/dist/index.js");
/* harmony import */ var _honey_pot_fix_get_element_from_point_without_honey_pot__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ../honey-pot-fix/get-element-from-point-without-honey-pot */ "./node_modules/@atlaskit/pragmatic-drag-and-drop/dist/esm/honey-pot-fix/get-element-from-point-without-honey-pot.js");
/* harmony import */ var _honey_pot_fix_make_honey_pot_fix__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ../honey-pot-fix/make-honey-pot-fix */ "./node_modules/@atlaskit/pragmatic-drag-and-drop/dist/esm/honey-pot-fix/make-honey-pot-fix.js");
/* harmony import */ var _make_adapter_make_adapter__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ../make-adapter/make-adapter */ "./node_modules/@atlaskit/pragmatic-drag-and-drop/dist/esm/make-adapter/make-adapter.js");
/* harmony import */ var _public_utils_combine__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ../public-utils/combine */ "./node_modules/@atlaskit/pragmatic-drag-and-drop/dist/esm/public-utils/combine.js");
/* harmony import */ var _public_utils_once__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! ../public-utils/once */ "./node_modules/@atlaskit/pragmatic-drag-and-drop/dist/esm/public-utils/once.js");
/* harmony import */ var _util_add_attribute__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(/*! ../util/add-attribute */ "./node_modules/@atlaskit/pragmatic-drag-and-drop/dist/esm/util/add-attribute.js");
/* harmony import */ var _util_android__WEBPACK_IMPORTED_MODULE_8__ = __webpack_require__(/*! ../util/android */ "./node_modules/@atlaskit/pragmatic-drag-and-drop/dist/esm/util/android.js");
/* harmony import */ var _util_get_input__WEBPACK_IMPORTED_MODULE_9__ = __webpack_require__(/*! ../util/get-input */ "./node_modules/@atlaskit/pragmatic-drag-and-drop/dist/esm/util/get-input.js");
/* harmony import */ var _util_media_types_text_media_type__WEBPACK_IMPORTED_MODULE_10__ = __webpack_require__(/*! ../util/media-types/text-media-type */ "./node_modules/@atlaskit/pragmatic-drag-and-drop/dist/esm/util/media-types/text-media-type.js");
/* harmony import */ var _util_media_types_url_media_type__WEBPACK_IMPORTED_MODULE_11__ = __webpack_require__(/*! ../util/media-types/url-media-type */ "./node_modules/@atlaskit/pragmatic-drag-and-drop/dist/esm/util/media-types/url-media-type.js");
/* harmony import */ var _element_adapter_native_data_key__WEBPACK_IMPORTED_MODULE_12__ = __webpack_require__(/*! ./element-adapter-native-data-key */ "./node_modules/@atlaskit/pragmatic-drag-and-drop/dist/esm/adapter/element-adapter-native-data-key.js");













var draggableRegistry = new WeakMap();
function addToRegistry(args) {
  draggableRegistry.set(args.element, args);
  return function cleanup() {
    draggableRegistry.delete(args.element);
  };
}
var honeyPotFix = (0,_honey_pot_fix_make_honey_pot_fix__WEBPACK_IMPORTED_MODULE_3__.makeHoneyPotFix)();
var adapter = (0,_make_adapter_make_adapter__WEBPACK_IMPORTED_MODULE_4__.makeAdapter)({
  typeKey: 'element',
  defaultDropEffect: 'move',
  mount: function mount(api) {
    /**  Binding event listeners the `document` rather than `window` so that
     * this adapter always gets preference over the text adapter.
     * `document` is the first `EventTarget` under `window`
     * https://twitter.com/alexandereardon/status/1604658588311465985
     */
    return (0,_public_utils_combine__WEBPACK_IMPORTED_MODULE_5__.combine)(honeyPotFix.bindEvents(), (0,bind_event_listener__WEBPACK_IMPORTED_MODULE_1__.bind)(document, {
      type: 'dragstart',
      listener: function listener(event) {
        var _entry$dragHandle, _entry$getInitialData, _entry$getInitialData2, _entry$dragHandle2, _entry$getInitialData3, _entry$getInitialData4;
        if (!api.canStart(event)) {
          return;
        }

        // If the "dragstart" event is cancelled, then a drag won't start
        // There will be no further drag operation events (eg no "dragend" event)
        if (event.defaultPrevented) {
          return;
        }

        // Technically `dataTransfer` can be `null` according to the types
        // But that behaviour does not seem to appear in the spec.
        // If there is not `dataTransfer`, we can assume something is wrong and not
        // start a drag
        if (!event.dataTransfer) {
          // Including this code on "test" and "development" environments:
          // - Browser tests commonly run against "development" builds
          // - Unit tests commonly run in "test"
          if (true) {
            // eslint-disable-next-line no-console
            console.warn("\n              It appears as though you have are not testing DragEvents correctly.\n\n              - If you are unit testing, ensure you have polyfilled DragEvent.\n              - If you are browser testing, ensure you are dispatching drag events correctly.\n\n              Please see our testing guides for more information:\n              https://atlassian.design/components/pragmatic-drag-and-drop/core-package/testing\n            ".replace(/ {2}/g, ''));
          }
          return;
        }

        // the closest parent that is a draggable element will be marked as
        // the `event.target` for the event
        var target = event.target;

        // this source is only for elements
        // Note: only HTMLElements can have the "draggable" attribute
        if (!(target instanceof HTMLElement)) {
          return;
        }

        // see if the thing being dragged is owned by us
        var entry = draggableRegistry.get(target);

        // no matching element found
        // → dragging an element with `draggable="true"` that is not controlled by us
        if (!entry) {
          return;
        }

        /**
         * A text selection drag _can_ have the `draggable` element be
         * the `event.target` if the user is dragging the text selection
         * from the `draggable`.
         *
         * To know if the `draggable` is being dragged, we look at whether any
         * `"text/plain"` data is being dragged. If it is, then a text selection
         * drag is occurring.
         *
         * This behaviour has been validated on:
         *
         * - Chrome@128 on Android@14
         * - Chrome@128 on iOS@17.6.1
         * - Chrome@128 on Windows@11
         * - Chrome@128 on MacOS@14.6.1
         * - Firefox@129 on Windows@11 (not possible for user to select text in a draggable)
         * - Firefox@129 on MacOS@14.6.1 (not possible for user to select text in a draggable)
         *
         * Note: Could usually just use: `event.dataTransfer.types.includes(textMediaType)`
         * but unfortunately ProseMirror is always setting `""` as the dragged text
         *
         * Note: Unfortunately editor is (heavily) leaning on the current functionality today
         * and unwinding it will be a decent amount of effort. So for now, a text selection
         * where the `event.target` is a `draggable` element will still trigger the
         * element adapter.
         *
         * // Future state:
         * if(event.dataTransfer.getData(textMediaType)) {
         * 	return;
         * }
         *
         */

        var input = (0,_util_get_input__WEBPACK_IMPORTED_MODULE_9__.getInput)(event);
        var feedback = {
          element: entry.element,
          dragHandle: (_entry$dragHandle = entry.dragHandle) !== null && _entry$dragHandle !== void 0 ? _entry$dragHandle : null,
          input: input
        };

        // Check: does the draggable want to allow dragging?
        if (entry.canDrag && !entry.canDrag(feedback)) {
          // cancel drag operation if we cannot drag
          event.preventDefault();
          return;
        }

        // Check: is there a drag handle and is the user using it?
        if (entry.dragHandle) {
          // technically don't need this util, but just being
          // consistent with how we look up what is under the users
          // cursor.
          var over = (0,_honey_pot_fix_get_element_from_point_without_honey_pot__WEBPACK_IMPORTED_MODULE_2__.getElementFromPointWithoutHoneypot)({
            x: input.clientX,
            y: input.clientY
          });

          // if we are not dragging from the drag handle (or something inside the drag handle)
          // then we will cancel the active drag
          if (!entry.dragHandle.contains(over)) {
            event.preventDefault();
            return;
          }
        }

        /**
         *  **Goal**
         *  Pass information to other applications
         *
         * **Approach**
         *  Put data into the native data store
         *
         *  **What about the native adapter?**
         *  When the element adapter puts native data into the native data store
         *  the native adapter is not triggered in the current window,
         *  but a native adapter in an external window _can_ be triggered
         *
         *  **Why bake this into core?**
         *  This functionality could be pulled out and exposed inside of
         *  `onGenerateDragPreview`. But decided to make it a part of the
         *  base API as it felt like a common enough use case and ended
         *  up being a similar amount of code to include this function as
         *  it was to expose the hook for it
         */
        var nativeData = (_entry$getInitialData = (_entry$getInitialData2 = entry.getInitialDataForExternal) === null || _entry$getInitialData2 === void 0 ? void 0 : _entry$getInitialData2.call(entry, feedback)) !== null && _entry$getInitialData !== void 0 ? _entry$getInitialData : null;
        if (nativeData) {
          for (var _i = 0, _Object$entries = Object.entries(nativeData); _i < _Object$entries.length; _i++) {
            var _Object$entries$_i = (0,_babel_runtime_helpers_slicedToArray__WEBPACK_IMPORTED_MODULE_0__["default"])(_Object$entries[_i], 2),
              key = _Object$entries$_i[0],
              data = _Object$entries$_i[1];
            event.dataTransfer.setData(key, data !== null && data !== void 0 ? data : '');
          }
        }

        /**
         *  📱 For Android devices, a drag operation will not start unless
         * "text/plain" or "text/uri-list" data exists in the native data store
         * https://twitter.com/alexandereardon/status/1732189803754713424
         *
         * Tested on:
         * Device: Google Pixel 5
         * Android version: 14 (November 5, 2023)
         * Chrome version: 120.0
         */
        if ((0,_util_android__WEBPACK_IMPORTED_MODULE_8__.isAndroid)() && !event.dataTransfer.types.includes(_util_media_types_text_media_type__WEBPACK_IMPORTED_MODULE_10__.textMediaType) && !event.dataTransfer.types.includes(_util_media_types_url_media_type__WEBPACK_IMPORTED_MODULE_11__.URLMediaType)) {
          event.dataTransfer.setData(_util_media_types_text_media_type__WEBPACK_IMPORTED_MODULE_10__.textMediaType, _util_android__WEBPACK_IMPORTED_MODULE_8__.androidFallbackText);
        }

        /**
         * 1. Must set any media type for `iOS15` to work
         * 2. We are also doing adding data so that the native adapter
         * can know that the element adapter has handled this drag
         *
         * We used to wrap this `setData()` in a `try/catch` for Firefox,
         * but it looks like that was not needed.
         *
         * Tested using: https://codesandbox.io/s/checking-firefox-throw-behaviour-on-dragstart-qt8h4f
         *
         * - ✅ Firefox@70.0 (Oct 2019) on macOS Sonoma
         * - ✅ Firefox@70.0 (Oct 2019) on macOS Big Sur
         * - ✅ Firefox@70.0 (Oct 2019) on Windows 10
         *
         * // just checking a few more combinations to be super safe
         *
         * - ✅ Chrome@78 (Oct 2019) on macOS Big Sur
         * - ✅ Chrome@78 (Oct 2019) on Windows 10
         * - ✅ Safari@14.1 on macOS Big Sur
         */
        event.dataTransfer.setData(_element_adapter_native_data_key__WEBPACK_IMPORTED_MODULE_12__.elementAdapterNativeDataKey, '');
        var payload = {
          element: entry.element,
          dragHandle: (_entry$dragHandle2 = entry.dragHandle) !== null && _entry$dragHandle2 !== void 0 ? _entry$dragHandle2 : null,
          data: (_entry$getInitialData3 = (_entry$getInitialData4 = entry.getInitialData) === null || _entry$getInitialData4 === void 0 ? void 0 : _entry$getInitialData4.call(entry, feedback)) !== null && _entry$getInitialData3 !== void 0 ? _entry$getInitialData3 : {}
        };
        var dragType = {
          type: 'element',
          payload: payload,
          startedFrom: 'internal'
        };
        api.start({
          event: event,
          dragType: dragType
        });
      }
    }));
  },
  dispatchEventToSource: function dispatchEventToSource(_ref) {
    var _draggableRegistry$ge, _draggableRegistry$ge2;
    var eventName = _ref.eventName,
      payload = _ref.payload;
    // During a drag operation, a draggable can be:
    // - remounted with different functions
    // - removed completely
    // So we need to get the latest entry from the registry in order
    // to call the latest event functions

    (_draggableRegistry$ge = draggableRegistry.get(payload.source.element)) === null || _draggableRegistry$ge === void 0 || (_draggableRegistry$ge2 = _draggableRegistry$ge[eventName]) === null || _draggableRegistry$ge2 === void 0 || _draggableRegistry$ge2.call(_draggableRegistry$ge,
    // I cannot seem to get the types right here.
    // TS doesn't seem to like that one event can need `nativeSetDragImage`
    // @ts-expect-error
    payload);
  },
  onPostDispatch: honeyPotFix.getOnPostDispatch()
});
var dropTargetForElements = adapter.dropTarget;
var monitorForElements = adapter.monitor;
function draggable(args) {
  // Guardrail: warn if the drag handle is not contained in draggable element
  if (true) {
    if (args.dragHandle && !args.element.contains(args.dragHandle)) {
      // eslint-disable-next-line no-console
      console.warn('Drag handle element must be contained in draggable element', {
        element: args.element,
        dragHandle: args.dragHandle
      });
    }
  }
  // Guardrail: warn if the draggable element is already registered
  if (true) {
    var existing = draggableRegistry.get(args.element);
    if (existing) {
      // eslint-disable-next-line no-console
      console.warn('You have already registered a `draggable` on the same element', {
        existing: existing,
        proposed: args
      });
    }
  }
  var cleanup = (0,_public_utils_combine__WEBPACK_IMPORTED_MODULE_5__.combine)(
  // making the draggable register the adapter rather than drop targets
  // this is because you *must* have a draggable element to start a drag
  // but you _might_ not have any drop targets immediately
  // (You might create drop targets async)
  adapter.registerUsage(), addToRegistry(args), (0,_util_add_attribute__WEBPACK_IMPORTED_MODULE_7__.addAttribute)(args.element, {
    attribute: 'draggable',
    value: 'true'
  }));

  // Wrapping in `once` to prevent unexpected side effects if consumers call
  // the clean up function multiple times.
  return (0,_public_utils_once__WEBPACK_IMPORTED_MODULE_6__.once)(cleanup);
}

/** Common event payload for all events */

/** A map containing payloads for all events */

/** Common event payload for all drop target events */

/** A map containing payloads for all events on drop targets */

/** Arguments given to all feedback functions (eg `canDrag()`) on for a `draggable()` */

/** Arguments given to all feedback functions (eg `canDrop()`) on a `dropTargetForElements()` */

/** Arguments given to all monitor feedback functions (eg `canMonitor()`) for a `monitorForElements` */

/***/ }),

/***/ "./node_modules/@atlaskit/pragmatic-drag-and-drop/dist/esm/entry-point/combine.js":
/*!****************************************************************************************!*\
  !*** ./node_modules/@atlaskit/pragmatic-drag-and-drop/dist/esm/entry-point/combine.js ***!
  \****************************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   combine: () => (/* reexport safe */ _public_utils_combine__WEBPACK_IMPORTED_MODULE_0__.combine)
/* harmony export */ });
/* harmony import */ var _public_utils_combine__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ../public-utils/combine */ "./node_modules/@atlaskit/pragmatic-drag-and-drop/dist/esm/public-utils/combine.js");


/***/ }),

/***/ "./node_modules/@atlaskit/pragmatic-drag-and-drop/dist/esm/entry-point/element/adapter.js":
/*!************************************************************************************************!*\
  !*** ./node_modules/@atlaskit/pragmatic-drag-and-drop/dist/esm/entry-point/element/adapter.js ***!
  \************************************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   draggable: () => (/* reexport safe */ _adapter_element_adapter__WEBPACK_IMPORTED_MODULE_0__.draggable),
/* harmony export */   dropTargetForElements: () => (/* reexport safe */ _adapter_element_adapter__WEBPACK_IMPORTED_MODULE_0__.dropTargetForElements),
/* harmony export */   monitorForElements: () => (/* reexport safe */ _adapter_element_adapter__WEBPACK_IMPORTED_MODULE_0__.monitorForElements)
/* harmony export */ });
/* harmony import */ var _adapter_element_adapter__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ../../adapter/element-adapter */ "./node_modules/@atlaskit/pragmatic-drag-and-drop/dist/esm/adapter/element-adapter.js");


// Payload for the draggable being dragged

/***/ }),

/***/ "./node_modules/@atlaskit/pragmatic-drag-and-drop/dist/esm/entry-point/once.js":
/*!*************************************************************************************!*\
  !*** ./node_modules/@atlaskit/pragmatic-drag-and-drop/dist/esm/entry-point/once.js ***!
  \*************************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   once: () => (/* reexport safe */ _public_utils_once__WEBPACK_IMPORTED_MODULE_0__.once)
/* harmony export */ });
/* harmony import */ var _public_utils_once__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ../public-utils/once */ "./node_modules/@atlaskit/pragmatic-drag-and-drop/dist/esm/public-utils/once.js");


/***/ }),

/***/ "./node_modules/@atlaskit/pragmatic-drag-and-drop/dist/esm/entry-point/private/get-element-from-point-without-honey-pot.js":
/*!*********************************************************************************************************************************!*\
  !*** ./node_modules/@atlaskit/pragmatic-drag-and-drop/dist/esm/entry-point/private/get-element-from-point-without-honey-pot.js ***!
  \*********************************************************************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   getElementFromPointWithoutHoneypot: () => (/* reexport safe */ _honey_pot_fix_get_element_from_point_without_honey_pot__WEBPACK_IMPORTED_MODULE_0__.getElementFromPointWithoutHoneypot)
/* harmony export */ });
/* harmony import */ var _honey_pot_fix_get_element_from_point_without_honey_pot__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ../../honey-pot-fix/get-element-from-point-without-honey-pot */ "./node_modules/@atlaskit/pragmatic-drag-and-drop/dist/esm/honey-pot-fix/get-element-from-point-without-honey-pot.js");


/***/ }),

/***/ "./node_modules/@atlaskit/pragmatic-drag-and-drop/dist/esm/honey-pot-fix/get-element-from-point-without-honey-pot.js":
/*!***************************************************************************************************************************!*\
  !*** ./node_modules/@atlaskit/pragmatic-drag-and-drop/dist/esm/honey-pot-fix/get-element-from-point-without-honey-pot.js ***!
  \***************************************************************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   getElementFromPointWithoutHoneypot: () => (/* binding */ getElementFromPointWithoutHoneypot)
/* harmony export */ });
/* harmony import */ var _babel_runtime_helpers_slicedToArray__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @babel/runtime/helpers/slicedToArray */ "./node_modules/@babel/runtime/helpers/esm/slicedToArray.js");
/* harmony import */ var _is_honey_pot_element__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./is-honey-pot-element */ "./node_modules/@atlaskit/pragmatic-drag-and-drop/dist/esm/honey-pot-fix/is-honey-pot-element.js");


function getElementFromPointWithoutHoneypot(client) {
  // eslint-disable-next-line no-restricted-syntax
  var _document$elementsFro = document.elementsFromPoint(client.x, client.y),
    _document$elementsFro2 = (0,_babel_runtime_helpers_slicedToArray__WEBPACK_IMPORTED_MODULE_0__["default"])(_document$elementsFro, 2),
    top = _document$elementsFro2[0],
    second = _document$elementsFro2[1];
  if (!top) {
    return null;
  }
  if ((0,_is_honey_pot_element__WEBPACK_IMPORTED_MODULE_1__.isHoneyPotElement)(top)) {
    return second !== null && second !== void 0 ? second : null;
  }
  return top;
}

/***/ }),

/***/ "./node_modules/@atlaskit/pragmatic-drag-and-drop/dist/esm/honey-pot-fix/honey-pot-data-attribute.js":
/*!***********************************************************************************************************!*\
  !*** ./node_modules/@atlaskit/pragmatic-drag-and-drop/dist/esm/honey-pot-fix/honey-pot-data-attribute.js ***!
  \***********************************************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   honeyPotDataAttribute: () => (/* binding */ honeyPotDataAttribute)
/* harmony export */ });
// pulling this into a separate file so adapter(s) that don't
// need the honey pot can pay as little as possible for it.
var honeyPotDataAttribute = 'data-pdnd-honey-pot';

/***/ }),

/***/ "./node_modules/@atlaskit/pragmatic-drag-and-drop/dist/esm/honey-pot-fix/is-honey-pot-element.js":
/*!*******************************************************************************************************!*\
  !*** ./node_modules/@atlaskit/pragmatic-drag-and-drop/dist/esm/honey-pot-fix/is-honey-pot-element.js ***!
  \*******************************************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   isHoneyPotElement: () => (/* binding */ isHoneyPotElement)
/* harmony export */ });
/* harmony import */ var _honey_pot_data_attribute__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./honey-pot-data-attribute */ "./node_modules/@atlaskit/pragmatic-drag-and-drop/dist/esm/honey-pot-fix/honey-pot-data-attribute.js");

function isHoneyPotElement(target) {
  return target instanceof Element && target.hasAttribute(_honey_pot_data_attribute__WEBPACK_IMPORTED_MODULE_0__.honeyPotDataAttribute);
}

/***/ }),

/***/ "./node_modules/@atlaskit/pragmatic-drag-and-drop/dist/esm/honey-pot-fix/make-honey-pot-fix.js":
/*!*****************************************************************************************************!*\
  !*** ./node_modules/@atlaskit/pragmatic-drag-and-drop/dist/esm/honey-pot-fix/make-honey-pot-fix.js ***!
  \*****************************************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   makeHoneyPotFix: () => (/* binding */ makeHoneyPotFix)
/* harmony export */ });
/* harmony import */ var _babel_runtime_helpers_defineProperty__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @babel/runtime/helpers/defineProperty */ "./node_modules/@babel/runtime/helpers/esm/defineProperty.js");
/* harmony import */ var bind_event_listener__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! bind-event-listener */ "./node_modules/bind-event-listener/dist/index.js");
/* harmony import */ var _util_max_z_index__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ../util/max-z-index */ "./node_modules/@atlaskit/pragmatic-drag-and-drop/dist/esm/util/max-z-index.js");
/* harmony import */ var _honey_pot_data_attribute__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ./honey-pot-data-attribute */ "./node_modules/@atlaskit/pragmatic-drag-and-drop/dist/esm/honey-pot-fix/honey-pot-data-attribute.js");

function ownKeys(e, r) { var t = Object.keys(e); if (Object.getOwnPropertySymbols) { var o = Object.getOwnPropertySymbols(e); r && (o = o.filter(function (r) { return Object.getOwnPropertyDescriptor(e, r).enumerable; })), t.push.apply(t, o); } return t; }
function _objectSpread(e) { for (var r = 1; r < arguments.length; r++) { var t = null != arguments[r] ? arguments[r] : {}; r % 2 ? ownKeys(Object(t), !0).forEach(function (r) { (0,_babel_runtime_helpers_defineProperty__WEBPACK_IMPORTED_MODULE_0__["default"])(e, r, t[r]); }) : Object.getOwnPropertyDescriptors ? Object.defineProperties(e, Object.getOwnPropertyDescriptors(t)) : ownKeys(Object(t)).forEach(function (r) { Object.defineProperty(e, r, Object.getOwnPropertyDescriptor(t, r)); }); } return e; }



var honeyPotSize = 2;
var halfHoneyPotSize = honeyPotSize / 2;

/**
 * `clientX` and `clientY` can be in sub pixels (eg `2.332`)
 * However, browser hitbox testing is commonly do to the closest pixel.
 *
 * → https://issues.chromium.org/issues/40940531
 *
 * To be sure that the honey pot will be over the `client` position,
 * we `.floor()` `clientX` and`clientY` and then make it `2px` in size.
 **/
function floorToClosestPixel(point) {
  return {
    x: Math.floor(point.x),
    y: Math.floor(point.y)
  };
}

/**
 * We want to make sure the honey pot sits around the users position.
 * This seemed to be the most resilient while testing.
 */
function pullBackByHalfHoneyPotSize(point) {
  return {
    x: point.x - halfHoneyPotSize,
    y: point.y - halfHoneyPotSize
  };
}

/**
 * Prevent the honey pot from changing the window size.
 * This is super unlikely to occur, but just being safe.
 */
function preventGoingBackwardsOffScreen(point) {
  return {
    x: Math.max(point.x, 0),
    y: Math.max(point.y, 0)
  };
}

/**
 * Prevent the honey pot from changing the window size.
 * This is super unlikely to occur, but just being safe.
 */
function preventGoingForwardsOffScreen(point) {
  return {
    x: Math.min(point.x, window.innerWidth - honeyPotSize),
    y: Math.min(point.y, window.innerHeight - honeyPotSize)
  };
}

/**
 * Create a `2x2` `DOMRect` around the `client` position
 */
function getHoneyPotRectFor(_ref) {
  var client = _ref.client;
  var point = preventGoingForwardsOffScreen(preventGoingBackwardsOffScreen(pullBackByHalfHoneyPotSize(floorToClosestPixel(client))));

  // When debugging, it is helpful to
  // make this element a bit bigger
  return DOMRect.fromRect({
    x: point.x,
    y: point.y,
    width: honeyPotSize,
    height: honeyPotSize
  });
}
function getRectStyles(_ref2) {
  var clientRect = _ref2.clientRect;
  return {
    left: "".concat(clientRect.left, "px"),
    top: "".concat(clientRect.top, "px"),
    width: "".concat(clientRect.width, "px"),
    height: "".concat(clientRect.height, "px")
  };
}
function isWithin(_ref3) {
  var client = _ref3.client,
    clientRect = _ref3.clientRect;
  return (
    // is within horizontal bounds
    client.x >= clientRect.x && client.x <= clientRect.x + clientRect.width &&
    // is within vertical bounds
    client.y >= clientRect.y && client.y <= clientRect.y + clientRect.height
  );
}
/**
 * The honey pot fix is designed to get around a painful bug in all browsers.
 *
 * [Overview](https://www.youtube.com/watch?v=udE9qbFTeQg)
 *
 * **Background**
 *
 * When a drag starts, browsers incorrectly think that the users pointer is
 * still depressed where the drag started. Any element that goes under this position
 * will be entered into, causing `"mouseenter"` events and `":hover"` styles to be applied.
 *
 * _This is a violation of the spec_
 *
 * > "From the moment that the user agent is to initiate the drag-and-drop operation,
 * > until the end 	of the drag-and-drop operation, device input events
 * > (e.g. mouse and keyboard events) must be suppressed."
 * >
 * > - https://html.spec.whatwg.org/multipage/dnd.html#drag-and-drop-processing-model
 *
 * _Some impacts_
 *
 * - `":hover"` styles being applied where they shouldn't (looks messy)
 * - components such as tooltips responding to `"mouseenter"` can show during a drag,
 *   and on an element the user isn't even over
 *
 * Bug: https://issues.chromium.org/issues/41129937
 *
 * **Honey pot fix**
 *
 * 1. Create an element where the browser thinks the depressed pointer is
 *    to absorb the incorrect pointer events
 * 2. Remove that element when it is no longer needed
 */
function mountHoneyPot(_ref4) {
  var initial = _ref4.initial;
  var element = document.createElement('div');
  element.setAttribute(_honey_pot_data_attribute__WEBPACK_IMPORTED_MODULE_3__.honeyPotDataAttribute, 'true');

  // can shift during the drag thanks to Firefox
  var clientRect = getHoneyPotRectFor({
    client: initial
  });
  Object.assign(element.style, _objectSpread(_objectSpread({
    // Setting a background color explicitly to avoid any inherited styles.
    // Looks like this could be `opacity: 0`, but worried that _might_
    // cause the element to be ignored on some platforms.
    // When debugging, set backgroundColor to something like "red".
    backgroundColor: 'transparent',
    position: 'fixed',
    // Being explicit to avoid inheriting styles
    padding: 0,
    margin: 0,
    boxSizing: 'border-box'
  }, getRectStyles({
    clientRect: clientRect
  })), {}, {
    // We want this element to absorb pointer events,
    // it's kind of the whole point 😉
    pointerEvents: 'auto',
    // Want to make sure the honey pot is top of everything else.
    // Don't need to worry about native drag previews, as they will
    // have been rendered (and removed) before the honey pot is rendered
    zIndex: _util_max_z_index__WEBPACK_IMPORTED_MODULE_2__.maxZIndex
  }));
  document.body.appendChild(element);

  /**
   *  🦊 In firefox we can get `"pointermove"` events after the drag
   * has started, which is a spec violation.
   * The final `"pointermove"` will reveal where the "depressed" position
   * is for our honey pot fix.
   */
  var unbindPointerMove = (0,bind_event_listener__WEBPACK_IMPORTED_MODULE_1__.bind)(window, {
    type: 'pointermove',
    listener: function listener(event) {
      var client = {
        x: event.clientX,
        y: event.clientY
      };
      clientRect = getHoneyPotRectFor({
        client: client
      });
      Object.assign(element.style, getRectStyles({
        clientRect: clientRect
      }));
    },
    // using capture so we are less likely to be impacted by event stopping
    options: {
      capture: true
    }
  });
  return function finish(_ref5) {
    var current = _ref5.current;
    // Don't need this any more
    unbindPointerMove();

    // If the user is hover the honey pot, we remove it
    // so that the user can continue to interact with the page normally.
    if (isWithin({
      client: current,
      clientRect: clientRect
    })) {
      element.remove();
      return;
    }
    function cleanup() {
      unbindPostDragEvents();
      element.remove();
    }
    var unbindPostDragEvents = (0,bind_event_listener__WEBPACK_IMPORTED_MODULE_1__.bindAll)(window, [{
      type: 'pointerdown',
      listener: cleanup
    }, {
      type: 'pointermove',
      listener: cleanup
    }, {
      type: 'focusin',
      listener: cleanup
    }, {
      type: 'focusout',
      listener: cleanup
    },
    // a 'pointerdown' should happen before 'dragstart', but just being super safe
    {
      type: 'dragstart',
      listener: cleanup
    },
    // if the user has dragged something out of the window
    // and then is dragging something back into the window
    // the first events we will see are "dragenter" (and then "dragover").
    // So if we see any of these we need to clear the post drag fix.
    {
      type: 'dragenter',
      listener: cleanup
    }, {
      type: 'dragover',
      listener: cleanup
    }

    // Not adding a "wheel" event listener, as "wheel" by itself does not
    // resolve the bug.
    ], {
      // Using `capture` so less likely to be impacted by other code stopping events
      capture: true
    });
  };
}
function makeHoneyPotFix() {
  var latestPointerMove = null;
  function bindEvents() {
    // For sanity, only collecting this value from when events are first bound.
    // This prevents the case where a super old "pointermove" could be used
    // from a prior interaction.
    latestPointerMove = null;
    return (0,bind_event_listener__WEBPACK_IMPORTED_MODULE_1__.bind)(window, {
      type: 'pointermove',
      listener: function listener(event) {
        latestPointerMove = {
          x: event.clientX,
          y: event.clientY
        };
      },
      // listening for pointer move in capture phase
      // so we are less likely to be impacted by events being stopped.
      options: {
        capture: true
      }
    });
  }
  function getOnPostDispatch() {
    var finish = null;
    return function onPostEvent(_ref6) {
      var eventName = _ref6.eventName,
        payload = _ref6.payload;
      // We are adding the honey pot `onDragStart` so we don't
      // impact the creation of the native drag preview.
      if (eventName === 'onDragStart') {
        var input = payload.location.initial.input;

        // Sometimes there will be no latest "pointermove" (eg iOS).
        // In which case, we use the start position of the drag.
        var initial = latestPointerMove !== null && latestPointerMove !== void 0 ? latestPointerMove : {
          x: input.clientX,
          y: input.clientY
        };

        // Don't need to defensively call `finish()` as `onDrop` from
        // one interaction is guaranteed to be called before `onDragStart`
        // of the next.
        finish = mountHoneyPot({
          initial: initial
        });
      }
      if (eventName === 'onDrop') {
        var _finish;
        var _input = payload.location.current.input;
        (_finish = finish) === null || _finish === void 0 || _finish({
          current: {
            x: _input.clientX,
            y: _input.clientY
          }
        });
        finish = null;
        // this interaction is finished, we want to use
        // the latest "pointermove" for each interaction
        latestPointerMove = null;
      }
    };
  }
  return {
    bindEvents: bindEvents,
    getOnPostDispatch: getOnPostDispatch
  };
}

/***/ }),

/***/ "./node_modules/@atlaskit/pragmatic-drag-and-drop/dist/esm/ledger/dispatch-consumer-event.js":
/*!***************************************************************************************************!*\
  !*** ./node_modules/@atlaskit/pragmatic-drag-and-drop/dist/esm/ledger/dispatch-consumer-event.js ***!
  \***************************************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   makeDispatch: () => (/* binding */ makeDispatch)
/* harmony export */ });
/* harmony import */ var raf_schd__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! raf-schd */ "./node_modules/raf-schd/dist/raf-schd.esm.js");

var scheduleOnDrag = (0,raf_schd__WEBPACK_IMPORTED_MODULE_0__["default"])(function (fn) {
  return fn();
});
var dragStart = function () {
  var scheduled = null;
  function schedule(fn) {
    var frameId = requestAnimationFrame(function () {
      scheduled = null;
      fn();
    });
    scheduled = {
      frameId: frameId,
      fn: fn
    };
  }
  function flush() {
    if (scheduled) {
      cancelAnimationFrame(scheduled.frameId);
      scheduled.fn();
      scheduled = null;
    }
  }
  return {
    schedule: schedule,
    flush: flush
  };
}();
function makeDispatch(_ref) {
  var source = _ref.source,
    initial = _ref.initial,
    dispatchEvent = _ref.dispatchEvent;
  var previous = {
    dropTargets: []
  };
  function safeDispatch(args) {
    dispatchEvent(args);
    previous = {
      dropTargets: args.payload.location.current.dropTargets
    };
  }
  var dispatch = {
    start: function start(_ref2) {
      var nativeSetDragImage = _ref2.nativeSetDragImage;
      // Ensuring that both `onGenerateDragPreview` and `onDragStart` get the same location.
      // We do this so that `previous` is`[]` in `onDragStart` (which is logical)
      var location = {
        current: initial,
        previous: previous,
        initial: initial
      };
      // a `onGenerateDragPreview` does _not_ add another entry for `previous`
      // onDragPreview
      safeDispatch({
        eventName: 'onGenerateDragPreview',
        payload: {
          source: source,
          location: location,
          nativeSetDragImage: nativeSetDragImage
        }
      });
      dragStart.schedule(function () {
        safeDispatch({
          eventName: 'onDragStart',
          payload: {
            source: source,
            location: location
          }
        });
      });
    },
    dragUpdate: function dragUpdate(_ref3) {
      var current = _ref3.current;
      dragStart.flush();
      scheduleOnDrag.cancel();
      safeDispatch({
        eventName: 'onDropTargetChange',
        payload: {
          source: source,
          location: {
            initial: initial,
            previous: previous,
            current: current
          }
        }
      });
    },
    drag: function drag(_ref4) {
      var current = _ref4.current;
      scheduleOnDrag(function () {
        dragStart.flush();
        var location = {
          initial: initial,
          previous: previous,
          current: current
        };
        safeDispatch({
          eventName: 'onDrag',
          payload: {
            source: source,
            location: location
          }
        });
      });
    },
    drop: function drop(_ref5) {
      var current = _ref5.current,
        updatedSourcePayload = _ref5.updatedSourcePayload;
      dragStart.flush();
      scheduleOnDrag.cancel();
      safeDispatch({
        eventName: 'onDrop',
        payload: {
          source: updatedSourcePayload !== null && updatedSourcePayload !== void 0 ? updatedSourcePayload : source,
          location: {
            current: current,
            previous: previous,
            initial: initial
          }
        }
      });
    }
  };
  return dispatch;
}

/***/ }),

/***/ "./node_modules/@atlaskit/pragmatic-drag-and-drop/dist/esm/ledger/lifecycle-manager.js":
/*!*********************************************************************************************!*\
  !*** ./node_modules/@atlaskit/pragmatic-drag-and-drop/dist/esm/ledger/lifecycle-manager.js ***!
  \*********************************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   lifecycle: () => (/* binding */ lifecycle)
/* harmony export */ });
/* harmony import */ var _babel_runtime_helpers_toConsumableArray__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @babel/runtime/helpers/toConsumableArray */ "./node_modules/@babel/runtime/helpers/esm/toConsumableArray.js");
/* harmony import */ var bind_event_listener__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! bind-event-listener */ "./node_modules/bind-event-listener/dist/index.js");
/* harmony import */ var _honey_pot_fix_get_element_from_point_without_honey_pot__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ../honey-pot-fix/get-element-from-point-without-honey-pot */ "./node_modules/@atlaskit/pragmatic-drag-and-drop/dist/esm/honey-pot-fix/get-element-from-point-without-honey-pot.js");
/* harmony import */ var _honey_pot_fix_is_honey_pot_element__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ../honey-pot-fix/is-honey-pot-element */ "./node_modules/@atlaskit/pragmatic-drag-and-drop/dist/esm/honey-pot-fix/is-honey-pot-element.js");
/* harmony import */ var _util_changing_window_is_leaving_window__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ../util/changing-window/is-leaving-window */ "./node_modules/@atlaskit/pragmatic-drag-and-drop/dist/esm/util/changing-window/is-leaving-window.js");
/* harmony import */ var _util_detect_broken_drag__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ../util/detect-broken-drag */ "./node_modules/@atlaskit/pragmatic-drag-and-drop/dist/esm/util/detect-broken-drag.js");
/* harmony import */ var _util_get_input__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! ../util/get-input */ "./node_modules/@atlaskit/pragmatic-drag-and-drop/dist/esm/util/get-input.js");
/* harmony import */ var _dispatch_consumer_event__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(/*! ./dispatch-consumer-event */ "./node_modules/@atlaskit/pragmatic-drag-and-drop/dist/esm/ledger/dispatch-consumer-event.js");








var globalState = {
  isActive: false
};
function canStart() {
  return !globalState.isActive;
}
function getNativeSetDragImage(event) {
  if (event.dataTransfer) {
    // need to use `.bind` as `setDragImage` is required
    // to be run with `event.dataTransfer` as the "this" context
    return event.dataTransfer.setDragImage.bind(event.dataTransfer);
  }
  return null;
}
function hasHierarchyChanged(_ref) {
  var current = _ref.current,
    next = _ref.next;
  if (current.length !== next.length) {
    return true;
  }
  // not checking stickiness, data or dropEffect,
  // just whether the hierarchy has changed
  for (var i = 0; i < current.length; i++) {
    if (current[i].element !== next[i].element) {
      return true;
    }
  }
  return false;
}
function start(_ref2) {
  var event = _ref2.event,
    dragType = _ref2.dragType,
    getDropTargetsOver = _ref2.getDropTargetsOver,
    dispatchEvent = _ref2.dispatchEvent;
  if (!canStart()) {
    return;
  }
  var initial = getStartLocation({
    event: event,
    dragType: dragType,
    getDropTargetsOver: getDropTargetsOver
  });
  globalState.isActive = true;
  var state = {
    current: initial
  };

  // Setting initial drop effect for the drag
  setDropEffectOnEvent({
    event: event,
    current: initial.dropTargets
  });
  var dispatch = (0,_dispatch_consumer_event__WEBPACK_IMPORTED_MODULE_7__.makeDispatch)({
    source: dragType.payload,
    dispatchEvent: dispatchEvent,
    initial: initial
  });
  function updateState(next) {
    // only looking at whether hierarchy has changed to determine whether something as 'changed'
    var hasChanged = hasHierarchyChanged({
      current: state.current.dropTargets,
      next: next.dropTargets
    });

    // Always updating the state to include latest data, dropEffect and stickiness
    // Only updating consumers if the hierarchy has changed in some way
    // Consumers can get the latest data by using `onDrag`
    state.current = next;
    if (hasChanged) {
      dispatch.dragUpdate({
        current: state.current
      });
    }
  }
  function onUpdateEvent(event) {
    var input = (0,_util_get_input__WEBPACK_IMPORTED_MODULE_6__.getInput)(event);

    // If we are over the honey pot, we need to get the element
    // that the user would have been over if not for the honey pot
    var target = (0,_honey_pot_fix_is_honey_pot_element__WEBPACK_IMPORTED_MODULE_3__.isHoneyPotElement)(event.target) ? (0,_honey_pot_fix_get_element_from_point_without_honey_pot__WEBPACK_IMPORTED_MODULE_2__.getElementFromPointWithoutHoneypot)({
      x: input.clientX,
      y: input.clientY
    }) : event.target;
    var nextDropTargets = getDropTargetsOver({
      target: target,
      input: input,
      source: dragType.payload,
      current: state.current.dropTargets
    });
    if (nextDropTargets.length) {
      // 🩸 must call `event.preventDefault()` to allow a browser drop to occur
      event.preventDefault();
      setDropEffectOnEvent({
        event: event,
        current: nextDropTargets
      });
    }
    updateState({
      dropTargets: nextDropTargets,
      input: input
    });
  }
  function cancel() {
    // The spec behaviour is that when a drag is cancelled, or when dropping on no drop targets,
    // a "dragleave" event is fired on the active drop target before a "dragend" event.
    // We are replicating that behaviour in `cancel` if there are any active drop targets to
    // ensure consistent behaviour.
    //
    // Note: When cancelling, or dropping on no drop targets, a "dragleave" event
    // will have already cleared the dropTargets to `[]` (as that particular "dragleave" has a `relatedTarget` of `null`)

    if (state.current.dropTargets.length) {
      updateState({
        dropTargets: [],
        input: state.current.input
      });
    }
    dispatch.drop({
      current: state.current,
      updatedSourcePayload: null
    });
    finish();
  }
  function finish() {
    globalState.isActive = false;
    unbindEvents();
  }
  var unbindEvents = (0,bind_event_listener__WEBPACK_IMPORTED_MODULE_1__.bindAll)(window, [{
    // 👋 Note: we are repurposing the `dragover` event as our `drag` event
    // this is because firefox does not publish pointer coordinates during
    // a `drag` event, but does for every other type of drag event
    // `dragover` fires on all elements that are being dragged over
    // Because we are binding to `window` - our `dragover` is effectively the same as a `drag`
    // 🦊😤
    type: 'dragover',
    listener: function listener(event) {
      // We need to regularly calculate the drop targets in order to allow:
      //  - dynamic `canDrop()` checks
      //  - rapid updating `getData()` calls to attach data in response to user input (eg for edge detection)
      // Sadly we cannot schedule inspecting changes resulting from this event
      // we need to be able to conditionally cancel the event with `event.preventDefault()`
      // to enable the correct native drop experience.

      // 1. check to see if anything has changed
      onUpdateEvent(event);

      // 2. let consumers know a move has occurred
      // This will include the latest 'input' values
      dispatch.drag({
        current: state.current
      });
    }
  }, {
    type: 'dragenter',
    listener: onUpdateEvent
  }, {
    type: 'dragleave',
    listener: function listener(event) {
      if (!(0,_util_changing_window_is_leaving_window__WEBPACK_IMPORTED_MODULE_4__.isLeavingWindow)({
        dragLeave: event
      })) {
        return;
      }

      /**
       * At this point we don't know if a drag is being cancelled,
       * or if a drag is leaving the `window`.
       *
       * Both have:
       *   1. "dragleave" (with `relatedTarget: null`)
       *   2. "dragend" (a "dragend" can occur when outside the `window`)
       *
       * **Clearing drop targets**
       *
       * For either case we are clearing the the drop targets
       *
       * - cancelling: we clear drop targets in `"dragend"` anyway
       * - leaving the `window`: we clear the drop targets (to clear stickiness)
       *
       * **Leaving the window and finishing the drag**
       *
       * _internal drags_
       *
       * - The drag continues when the user is outside the `window`
       *   and can resume if the user drags back over the `window`,
       *   or end when the user drops in an external `window`.
       * - We will get a `"dragend"`, or we can listen for other
       *   events to determine the drag is finished when the user re-enters the `window`).
       *
       * _external drags_
       *
       * - We conclude the drag operation.
       * - We have no idea if the user will drag back over the `window`,
       *   or if the drag ends elsewhere.
       * - We will create a new drag if the user re-enters the `window`.
       *
       * **Not updating `input`**
       *
       * 🐛 Bug[Chrome] the final `"dragleave"` has default input values (eg `clientX == 0`)
       * Workaround: intentionally not updating `input` in "dragleave"
       * rather than the users current input values
       * - [Conversation](https://twitter.com/alexandereardon/status/1642697633864241152)
       * - [Bug](https://bugs.chromium.org/p/chromium/issues/detail?id=1429937)
       **/

      updateState({
        input: state.current.input,
        dropTargets: []
      });
      if (dragType.startedFrom === 'external') {
        cancel();
      }
    }
  }, {
    // A "drop" can only happen if the browser allowed the drop
    type: 'drop',
    listener: function listener(event) {
      // Capture the final input.
      // We are capturing the final `input` for the
      // most accurate honey pot experience
      state.current = {
        dropTargets: state.current.dropTargets,
        input: (0,_util_get_input__WEBPACK_IMPORTED_MODULE_6__.getInput)(event)
      };

      /** If there are no drop targets, then we will get
       * a "drop" event if:
       * - `preventUnhandled()` is being used
       * - there is an unmanaged drop target (eg another library)
       * In these cases, it's up to the consumer
       * to handle the drop if it's not over one of our drop targets
       * - `preventUnhandled()` will cancel the "drop"
       * - unmanaged drop targets can handle the "drop" how they want to
       * We won't call `event.preventDefault()` in this call path */

      if (!state.current.dropTargets.length) {
        cancel();
        return;
      }
      event.preventDefault();

      // applying the latest drop effect to the event
      setDropEffectOnEvent({
        event: event,
        current: state.current.dropTargets
      });
      dispatch.drop({
        current: state.current,
        // When dropping something native, we need to extract the latest
        // `.items` from the "drop" event as it is now accessible
        updatedSourcePayload: dragType.type === 'external' ? dragType.getDropPayload(event) : null
      });
      finish();
    }
  }, {
    // "dragend" fires when on the drag source (eg a draggable element)
    // when the drag is finished.
    // "dragend" will fire after "drop" (if there was a successful drop)
    // "dragend" does not fire if the draggable source has been removed during the drag
    // or for external drag sources (eg files)

    // This "dragend" listener will not fire if there was a successful drop
    // as we will have already removed the event listener

    type: 'dragend',
    listener: function listener(event) {
      // In firefox, the position of the "dragend" event can
      // be a bit different to the last "dragover" event.
      // Updating the input so we can get the best possible
      // information for the honey pot.
      state.current = {
        dropTargets: state.current.dropTargets,
        input: (0,_util_get_input__WEBPACK_IMPORTED_MODULE_6__.getInput)(event)
      };
      cancel();
    }
  }].concat((0,_babel_runtime_helpers_toConsumableArray__WEBPACK_IMPORTED_MODULE_0__["default"])((0,_util_detect_broken_drag__WEBPACK_IMPORTED_MODULE_5__.getBindingsForBrokenDrags)({
    onDragEnd: cancel
  }))),
  // Once we have started a managed drag operation it is important that we see / own all drag events
  // We got one adoption bug pop up where some code was stopping (`event.stopPropagation()`)
  // all "drop" events in the bubble phase on the `document.body`.
  // This meant that we never saw the "drop" event.
  {
    capture: true
  });
  dispatch.start({
    nativeSetDragImage: getNativeSetDragImage(event)
  });
}
function setDropEffectOnEvent(_ref3) {
  var _current$;
  var event = _ref3.event,
    current = _ref3.current;
  // setting the `dropEffect` to be the innerMost drop targets dropEffect
  var innerMost = (_current$ = current[0]) === null || _current$ === void 0 ? void 0 : _current$.dropEffect;
  if (innerMost != null && event.dataTransfer) {
    event.dataTransfer.dropEffect = innerMost;
  }
}
function getStartLocation(_ref4) {
  var event = _ref4.event,
    dragType = _ref4.dragType,
    getDropTargetsOver = _ref4.getDropTargetsOver;
  var input = (0,_util_get_input__WEBPACK_IMPORTED_MODULE_6__.getInput)(event);

  // When dragging from outside of the browser,
  // the drag is not being sourced from any local drop targets
  if (dragType.startedFrom === 'external') {
    return {
      input: input,
      dropTargets: []
    };
  }
  var dropTargets = getDropTargetsOver({
    input: input,
    source: dragType.payload,
    target: event.target,
    current: []
  });
  return {
    input: input,
    dropTargets: dropTargets
  };
}
var lifecycle = {
  canStart: canStart,
  start: start
};

/***/ }),

/***/ "./node_modules/@atlaskit/pragmatic-drag-and-drop/dist/esm/ledger/usage-ledger.js":
/*!****************************************************************************************!*\
  !*** ./node_modules/@atlaskit/pragmatic-drag-and-drop/dist/esm/ledger/usage-ledger.js ***!
  \****************************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   register: () => (/* binding */ register)
/* harmony export */ });
// Extending `Map` to allow us to link Key and Values together

var ledger = new Map();
function registerUsage(_ref) {
  var typeKey = _ref.typeKey,
    mount = _ref.mount;
  var entry = ledger.get(typeKey);
  if (entry) {
    entry.usageCount++;
    return entry;
  }
  var initial = {
    typeKey: typeKey,
    unmount: mount(),
    usageCount: 1
  };
  ledger.set(typeKey, initial);
  return initial;
}
function register(args) {
  var entry = registerUsage(args);
  return function unregister() {
    entry.usageCount--;
    if (entry.usageCount > 0) {
      return;
    }
    // Only a single usage left, remove it
    entry.unmount();
    ledger.delete(args.typeKey);
  };
}

/***/ }),

/***/ "./node_modules/@atlaskit/pragmatic-drag-and-drop/dist/esm/make-adapter/make-adapter.js":
/*!**********************************************************************************************!*\
  !*** ./node_modules/@atlaskit/pragmatic-drag-and-drop/dist/esm/make-adapter/make-adapter.js ***!
  \**********************************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   makeAdapter: () => (/* binding */ makeAdapter)
/* harmony export */ });
/* harmony import */ var _ledger_lifecycle_manager__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ../ledger/lifecycle-manager */ "./node_modules/@atlaskit/pragmatic-drag-and-drop/dist/esm/ledger/lifecycle-manager.js");
/* harmony import */ var _ledger_usage_ledger__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ../ledger/usage-ledger */ "./node_modules/@atlaskit/pragmatic-drag-and-drop/dist/esm/ledger/usage-ledger.js");
/* harmony import */ var _make_drop_target__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./make-drop-target */ "./node_modules/@atlaskit/pragmatic-drag-and-drop/dist/esm/make-adapter/make-drop-target.js");
/* harmony import */ var _make_monitor__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ./make-monitor */ "./node_modules/@atlaskit/pragmatic-drag-and-drop/dist/esm/make-adapter/make-monitor.js");




function makeAdapter(_ref) {
  var typeKey = _ref.typeKey,
    mount = _ref.mount,
    dispatchEventToSource = _ref.dispatchEventToSource,
    onPostDispatch = _ref.onPostDispatch,
    defaultDropEffect = _ref.defaultDropEffect;
  var monitorAPI = (0,_make_monitor__WEBPACK_IMPORTED_MODULE_3__.makeMonitor)();
  var dropTargetAPI = (0,_make_drop_target__WEBPACK_IMPORTED_MODULE_2__.makeDropTarget)({
    typeKey: typeKey,
    defaultDropEffect: defaultDropEffect
  });
  function dispatchEvent(args) {
    // 1. forward the event to source
    dispatchEventToSource === null || dispatchEventToSource === void 0 || dispatchEventToSource(args);

    // 2. forward the event to relevant dropTargets
    dropTargetAPI.dispatchEvent(args);

    // 3. forward event to monitors
    monitorAPI.dispatchEvent(args);

    // 4. post consumer dispatch (used for honey pot fix)
    onPostDispatch === null || onPostDispatch === void 0 || onPostDispatch(args);
  }
  function start(_ref2) {
    var event = _ref2.event,
      dragType = _ref2.dragType;
    _ledger_lifecycle_manager__WEBPACK_IMPORTED_MODULE_0__.lifecycle.start({
      event: event,
      dragType: dragType,
      getDropTargetsOver: dropTargetAPI.getIsOver,
      dispatchEvent: dispatchEvent
    });
  }
  function registerUsage() {
    function mountAdapter() {
      var api = {
        canStart: _ledger_lifecycle_manager__WEBPACK_IMPORTED_MODULE_0__.lifecycle.canStart,
        start: start
      };
      return mount(api);
    }
    return (0,_ledger_usage_ledger__WEBPACK_IMPORTED_MODULE_1__.register)({
      typeKey: typeKey,
      mount: mountAdapter
    });
  }
  return {
    registerUsage: registerUsage,
    dropTarget: dropTargetAPI.dropTargetForConsumers,
    monitor: monitorAPI.monitorForConsumers
  };
}

/***/ }),

/***/ "./node_modules/@atlaskit/pragmatic-drag-and-drop/dist/esm/make-adapter/make-drop-target.js":
/*!**************************************************************************************************!*\
  !*** ./node_modules/@atlaskit/pragmatic-drag-and-drop/dist/esm/make-adapter/make-drop-target.js ***!
  \**************************************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   makeDropTarget: () => (/* binding */ makeDropTarget)
/* harmony export */ });
/* harmony import */ var _babel_runtime_helpers_defineProperty__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @babel/runtime/helpers/defineProperty */ "./node_modules/@babel/runtime/helpers/esm/defineProperty.js");
/* harmony import */ var _babel_runtime_helpers_toConsumableArray__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @babel/runtime/helpers/toConsumableArray */ "./node_modules/@babel/runtime/helpers/esm/toConsumableArray.js");
/* harmony import */ var _public_utils_combine__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ../public-utils/combine */ "./node_modules/@atlaskit/pragmatic-drag-and-drop/dist/esm/public-utils/combine.js");
/* harmony import */ var _public_utils_once__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ../public-utils/once */ "./node_modules/@atlaskit/pragmatic-drag-and-drop/dist/esm/public-utils/once.js");
/* harmony import */ var _util_add_attribute__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ../util/add-attribute */ "./node_modules/@atlaskit/pragmatic-drag-and-drop/dist/esm/util/add-attribute.js");


function ownKeys(e, r) { var t = Object.keys(e); if (Object.getOwnPropertySymbols) { var o = Object.getOwnPropertySymbols(e); r && (o = o.filter(function (r) { return Object.getOwnPropertyDescriptor(e, r).enumerable; })), t.push.apply(t, o); } return t; }
function _objectSpread(e) { for (var r = 1; r < arguments.length; r++) { var t = null != arguments[r] ? arguments[r] : {}; r % 2 ? ownKeys(Object(t), !0).forEach(function (r) { (0,_babel_runtime_helpers_defineProperty__WEBPACK_IMPORTED_MODULE_0__["default"])(e, r, t[r]); }) : Object.getOwnPropertyDescriptors ? Object.defineProperties(e, Object.getOwnPropertyDescriptors(t)) : ownKeys(Object(t)).forEach(function (r) { Object.defineProperty(e, r, Object.getOwnPropertyDescriptor(t, r)); }); } return e; }
function _createForOfIteratorHelper(r, e) { var t = "undefined" != typeof Symbol && r[Symbol.iterator] || r["@@iterator"]; if (!t) { if (Array.isArray(r) || (t = _unsupportedIterableToArray(r)) || e && r && "number" == typeof r.length) { t && (r = t); var _n = 0, F = function F() {}; return { s: F, n: function n() { return _n >= r.length ? { done: !0 } : { done: !1, value: r[_n++] }; }, e: function e(r) { throw r; }, f: F }; } throw new TypeError("Invalid attempt to iterate non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method."); } var o, a = !0, u = !1; return { s: function s() { t = t.call(r); }, n: function n() { var r = t.next(); return a = r.done, r; }, e: function e(r) { u = !0, o = r; }, f: function f() { try { a || null == t.return || t.return(); } finally { if (u) throw o; } } }; }
function _unsupportedIterableToArray(r, a) { if (r) { if ("string" == typeof r) return _arrayLikeToArray(r, a); var t = {}.toString.call(r).slice(8, -1); return "Object" === t && r.constructor && (t = r.constructor.name), "Map" === t || "Set" === t ? Array.from(r) : "Arguments" === t || /^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(t) ? _arrayLikeToArray(r, a) : void 0; } }
function _arrayLikeToArray(r, a) { (null == a || a > r.length) && (a = r.length); for (var e = 0, n = Array(a); e < a; e++) n[e] = r[e]; return n; }



function copyReverse(array) {
  return array.slice(0).reverse();
}
function makeDropTarget(_ref) {
  var typeKey = _ref.typeKey,
    defaultDropEffect = _ref.defaultDropEffect;
  var registry = new WeakMap();
  var dropTargetDataAtt = "data-drop-target-for-".concat(typeKey);
  var dropTargetSelector = "[".concat(dropTargetDataAtt, "]");
  function addToRegistry(args) {
    registry.set(args.element, args);
    return function () {
      return registry.delete(args.element);
    };
  }
  function dropTargetForConsumers(args) {
    // Guardrail: warn if the draggable element is already registered
    if (true) {
      var existing = registry.get(args.element);
      if (existing) {
        // eslint-disable-next-line no-console
        console.warn("You have already registered a [".concat(typeKey, "] dropTarget on the same element"), {
          existing: existing,
          proposed: args
        });
      }
      if (args.element instanceof HTMLIFrameElement) {
        // eslint-disable-next-line no-console
        console.warn("\n            We recommend not registering <iframe> elements as drop targets\n            as it can result in some strange browser event ordering.\n          " // Removing newlines and excessive whitespace
        .replace(/\s{2,}/g, ' ').trim());
      }
    }
    var cleanup = (0,_public_utils_combine__WEBPACK_IMPORTED_MODULE_2__.combine)((0,_util_add_attribute__WEBPACK_IMPORTED_MODULE_4__.addAttribute)(args.element, {
      attribute: dropTargetDataAtt,
      value: 'true'
    }), addToRegistry(args));

    // Wrapping in `once` to prevent unexpected side effects if consumers call
    // the clean up function multiple times.
    return (0,_public_utils_once__WEBPACK_IMPORTED_MODULE_3__.once)(cleanup);
  }
  function getActualDropTargets(_ref2) {
    var _args$getData, _args$getData2, _args$getDropEffect, _args$getDropEffect2;
    var source = _ref2.source,
      target = _ref2.target,
      input = _ref2.input,
      _ref2$result = _ref2.result,
      result = _ref2$result === void 0 ? [] : _ref2$result;
    if (target == null) {
      return result;
    }
    if (!(target instanceof Element)) {
      // For "text-selection" drags, the original `target`
      // is not an `Element`, so we need to start looking from
      // the parent element
      if (target instanceof Node) {
        return getActualDropTargets({
          source: source,
          target: target.parentElement,
          input: input,
          result: result
        });
      }

      // not sure what we are working with,
      // so we can exit.
      return result;
    }
    var closest = target.closest(dropTargetSelector);

    // Cannot find anything else
    if (closest == null) {
      return result;
    }
    var args = registry.get(closest);

    // error: something had a dropTargetSelector but we could not
    // find a match. For now, failing silently
    if (args == null) {
      return result;
    }
    var feedback = {
      input: input,
      source: source,
      element: args.element
    };

    // if dropping is not allowed, skip this drop target
    // and continue looking up the DOM tree
    if (args.canDrop && !args.canDrop(feedback)) {
      return getActualDropTargets({
        source: source,
        target: args.element.parentElement,
        input: input,
        result: result
      });
    }

    // calculate our new record
    var data = (_args$getData = (_args$getData2 = args.getData) === null || _args$getData2 === void 0 ? void 0 : _args$getData2.call(args, feedback)) !== null && _args$getData !== void 0 ? _args$getData : {};
    var dropEffect = (_args$getDropEffect = (_args$getDropEffect2 = args.getDropEffect) === null || _args$getDropEffect2 === void 0 ? void 0 : _args$getDropEffect2.call(args, feedback)) !== null && _args$getDropEffect !== void 0 ? _args$getDropEffect : defaultDropEffect;
    var record = {
      data: data,
      element: args.element,
      dropEffect: dropEffect,
      // we are collecting _actual_ drop targets, so these are
      // being applied _not_ due to stickiness
      isActiveDueToStickiness: false
    };
    return getActualDropTargets({
      source: source,
      target: args.element.parentElement,
      input: input,
      // Using bubble ordering. Same ordering as `event.getPath()`
      result: [].concat((0,_babel_runtime_helpers_toConsumableArray__WEBPACK_IMPORTED_MODULE_1__["default"])(result), [record])
    });
  }
  function notifyCurrent(_ref3) {
    var eventName = _ref3.eventName,
      payload = _ref3.payload;
    var _iterator = _createForOfIteratorHelper(payload.location.current.dropTargets),
      _step;
    try {
      for (_iterator.s(); !(_step = _iterator.n()).done;) {
        var _entry$eventName;
        var record = _step.value;
        var entry = registry.get(record.element);
        var args = _objectSpread(_objectSpread({}, payload), {}, {
          self: record
        });
        entry === null || entry === void 0 || (_entry$eventName = entry[eventName]) === null || _entry$eventName === void 0 || _entry$eventName.call(entry,
        // I cannot seem to get the types right here.
        // TS doesn't seem to like that one event can need `nativeSetDragImage`
        // @ts-expect-error
        args);
      }
    } catch (err) {
      _iterator.e(err);
    } finally {
      _iterator.f();
    }
  }
  var actions = {
    onGenerateDragPreview: notifyCurrent,
    onDrag: notifyCurrent,
    onDragStart: notifyCurrent,
    onDrop: notifyCurrent,
    onDropTargetChange: function onDropTargetChange(_ref4) {
      var payload = _ref4.payload;
      var isCurrent = new Set(payload.location.current.dropTargets.map(function (record) {
        return record.element;
      }));
      var visited = new Set();
      var _iterator2 = _createForOfIteratorHelper(payload.location.previous.dropTargets),
        _step2;
      try {
        for (_iterator2.s(); !(_step2 = _iterator2.n()).done;) {
          var _entry$onDropTargetCh;
          var record = _step2.value;
          visited.add(record.element);
          var entry = registry.get(record.element);
          var isOver = isCurrent.has(record.element);
          var args = _objectSpread(_objectSpread({}, payload), {}, {
            self: record
          });
          entry === null || entry === void 0 || (_entry$onDropTargetCh = entry.onDropTargetChange) === null || _entry$onDropTargetCh === void 0 || _entry$onDropTargetCh.call(entry, args);

          // if we cannot find the drop target in the current array, then it has been left
          if (!isOver) {
            var _entry$onDragLeave;
            entry === null || entry === void 0 || (_entry$onDragLeave = entry.onDragLeave) === null || _entry$onDragLeave === void 0 || _entry$onDragLeave.call(entry, args);
          }
        }
      } catch (err) {
        _iterator2.e(err);
      } finally {
        _iterator2.f();
      }
      var _iterator3 = _createForOfIteratorHelper(payload.location.current.dropTargets),
        _step3;
      try {
        for (_iterator3.s(); !(_step3 = _iterator3.n()).done;) {
          var _entry$onDropTargetCh2, _entry$onDragEnter;
          var _record = _step3.value;
          // already published an update to this drop target
          if (visited.has(_record.element)) {
            continue;
          }
          // at this point we have a new drop target that is being entered into
          var _args = _objectSpread(_objectSpread({}, payload), {}, {
            self: _record
          });
          var _entry = registry.get(_record.element);
          _entry === null || _entry === void 0 || (_entry$onDropTargetCh2 = _entry.onDropTargetChange) === null || _entry$onDropTargetCh2 === void 0 || _entry$onDropTargetCh2.call(_entry, _args);
          _entry === null || _entry === void 0 || (_entry$onDragEnter = _entry.onDragEnter) === null || _entry$onDragEnter === void 0 || _entry$onDragEnter.call(_entry, _args);
        }
      } catch (err) {
        _iterator3.e(err);
      } finally {
        _iterator3.f();
      }
    }
  };
  function dispatchEvent(args) {
    actions[args.eventName](args);
  }
  function getIsOver(_ref5) {
    var source = _ref5.source,
      target = _ref5.target,
      input = _ref5.input,
      current = _ref5.current;
    var actual = getActualDropTargets({
      source: source,
      target: target,
      input: input
    });

    // stickiness is only relevant when we have less
    // drop targets than we did before
    if (actual.length >= current.length) {
      return actual;
    }

    // less 'actual' drop targets than before,
    // we need to see if 'stickiness' applies

    // An old drop target will continue to be dropped on if:
    // 1. it has the same parent
    // 2. nothing exists in it's previous index

    var lastCaptureOrdered = copyReverse(current);
    var actualCaptureOrdered = copyReverse(actual);
    var resultCaptureOrdered = [];
    for (var index = 0; index < lastCaptureOrdered.length; index++) {
      var _argsForLast$getIsSti;
      var last = lastCaptureOrdered[index];
      var fresh = actualCaptureOrdered[index];

      // if a record is in the new index -> use that
      // it will have the latest data + dropEffect
      if (fresh != null) {
        resultCaptureOrdered.push(fresh);
        continue;
      }

      // At this point we have no drop target in the old spot
      // Check to see if we can use a previous sticky drop target

      // The "parent" is the one inside of `resultCaptureOrdered`
      // (the parent might be a drop target that was sticky)
      var parent = resultCaptureOrdered[index - 1];
      var lastParent = lastCaptureOrdered[index - 1];

      // Stickiness is based on parent relationships, so if the parent relationship has change
      // then we can stop our search
      if ((parent === null || parent === void 0 ? void 0 : parent.element) !== (lastParent === null || lastParent === void 0 ? void 0 : lastParent.element)) {
        break;
      }

      // We need to check whether the old drop target can still be dropped on

      var argsForLast = registry.get(last.element);

      // We cannot drop on a drop target that is no longer mounted
      if (!argsForLast) {
        break;
      }
      var feedback = {
        input: input,
        source: source,
        element: argsForLast.element
      };

      // We cannot drop on a drop target that no longer allows being dropped on
      if (argsForLast.canDrop && !argsForLast.canDrop(feedback)) {
        break;
      }

      // We cannot drop on a drop target that is no longer sticky
      if (!((_argsForLast$getIsSti = argsForLast.getIsSticky) !== null && _argsForLast$getIsSti !== void 0 && _argsForLast$getIsSti.call(argsForLast, feedback))) {
        break;
      }

      // Note: intentionally not recollecting `getData()` or `getDropEffect()`
      // Previous values for `data` and `dropEffect` will be borrowed
      // This is to prevent things like the 'closest edge' changing when
      // no longer over a drop target.
      // We could change our mind on this behaviour in the future

      resultCaptureOrdered.push(_objectSpread(_objectSpread({}, last), {}, {
        // making it clear to consumers this drop target is active due to stickiness
        isActiveDueToStickiness: true
      }));
    }

    // return bubble ordered result
    return copyReverse(resultCaptureOrdered);
  }
  return {
    dropTargetForConsumers: dropTargetForConsumers,
    getIsOver: getIsOver,
    dispatchEvent: dispatchEvent
  };
}

/***/ }),

/***/ "./node_modules/@atlaskit/pragmatic-drag-and-drop/dist/esm/make-adapter/make-monitor.js":
/*!**********************************************************************************************!*\
  !*** ./node_modules/@atlaskit/pragmatic-drag-and-drop/dist/esm/make-adapter/make-monitor.js ***!
  \**********************************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   makeMonitor: () => (/* binding */ makeMonitor)
/* harmony export */ });
/* harmony import */ var _babel_runtime_helpers_defineProperty__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @babel/runtime/helpers/defineProperty */ "./node_modules/@babel/runtime/helpers/esm/defineProperty.js");
/* harmony import */ var _public_utils_once__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ../public-utils/once */ "./node_modules/@atlaskit/pragmatic-drag-and-drop/dist/esm/public-utils/once.js");

function _createForOfIteratorHelper(r, e) { var t = "undefined" != typeof Symbol && r[Symbol.iterator] || r["@@iterator"]; if (!t) { if (Array.isArray(r) || (t = _unsupportedIterableToArray(r)) || e && r && "number" == typeof r.length) { t && (r = t); var _n = 0, F = function F() {}; return { s: F, n: function n() { return _n >= r.length ? { done: !0 } : { done: !1, value: r[_n++] }; }, e: function e(r) { throw r; }, f: F }; } throw new TypeError("Invalid attempt to iterate non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method."); } var o, a = !0, u = !1; return { s: function s() { t = t.call(r); }, n: function n() { var r = t.next(); return a = r.done, r; }, e: function e(r) { u = !0, o = r; }, f: function f() { try { a || null == t.return || t.return(); } finally { if (u) throw o; } } }; }
function _unsupportedIterableToArray(r, a) { if (r) { if ("string" == typeof r) return _arrayLikeToArray(r, a); var t = {}.toString.call(r).slice(8, -1); return "Object" === t && r.constructor && (t = r.constructor.name), "Map" === t || "Set" === t ? Array.from(r) : "Arguments" === t || /^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(t) ? _arrayLikeToArray(r, a) : void 0; } }
function _arrayLikeToArray(r, a) { (null == a || a > r.length) && (a = r.length); for (var e = 0, n = Array(a); e < a; e++) n[e] = r[e]; return n; }
function ownKeys(e, r) { var t = Object.keys(e); if (Object.getOwnPropertySymbols) { var o = Object.getOwnPropertySymbols(e); r && (o = o.filter(function (r) { return Object.getOwnPropertyDescriptor(e, r).enumerable; })), t.push.apply(t, o); } return t; }
function _objectSpread(e) { for (var r = 1; r < arguments.length; r++) { var t = null != arguments[r] ? arguments[r] : {}; r % 2 ? ownKeys(Object(t), !0).forEach(function (r) { (0,_babel_runtime_helpers_defineProperty__WEBPACK_IMPORTED_MODULE_0__["default"])(e, r, t[r]); }) : Object.getOwnPropertyDescriptors ? Object.defineProperties(e, Object.getOwnPropertyDescriptors(t)) : ownKeys(Object(t)).forEach(function (r) { Object.defineProperty(e, r, Object.getOwnPropertyDescriptor(t, r)); }); } return e; }

function makeMonitor() {
  var registry = new Set();
  var dragging = null;
  function tryAddToActive(monitor) {
    if (!dragging) {
      return;
    }
    // Monitor is allowed to monitor events if:
    // 1. It has no `canMonitor` function (default is that a monitor can listen to everything)
    // 2. `canMonitor` returns true
    if (!monitor.canMonitor || monitor.canMonitor(dragging.canMonitorArgs)) {
      dragging.active.add(monitor);
    }
  }
  function monitorForConsumers(args) {
    // We are giving each `args` a new reference so that you
    // can create multiple monitors with the same `args`.
    var entry = _objectSpread({}, args);
    registry.add(entry);

    // if there is an active drag we need to see if this new monitor is relevant
    tryAddToActive(entry);
    function cleanup() {
      registry.delete(entry);

      // We need to stop publishing events during a drag to this monitor!
      if (dragging) {
        dragging.active.delete(entry);
      }
    }

    // Wrapping in `once` to prevent unexpected side effects if consumers call
    // the clean up function multiple times.
    return (0,_public_utils_once__WEBPACK_IMPORTED_MODULE_1__.once)(cleanup);
  }
  function dispatchEvent(_ref) {
    var eventName = _ref.eventName,
      payload = _ref.payload;
    if (eventName === 'onGenerateDragPreview') {
      dragging = {
        canMonitorArgs: {
          initial: payload.location.initial,
          source: payload.source
        },
        active: new Set()
      };
      var _iterator = _createForOfIteratorHelper(registry),
        _step;
      try {
        for (_iterator.s(); !(_step = _iterator.n()).done;) {
          var monitor = _step.value;
          tryAddToActive(monitor);
        }
      } catch (err) {
        _iterator.e(err);
      } finally {
        _iterator.f();
      }
    }

    // This should never happen.
    if (!dragging) {
      return;
    }

    // Creating an array from the set _before_ iterating
    // This is so that monitors added during the current event will not be called.
    // This behaviour matches native EventTargets where an event listener
    // cannot add another event listener during an active event to the same
    // event target in the same event (for us we have a single global event target)
    var active = Array.from(dragging.active);
    for (var _i = 0, _active = active; _i < _active.length; _i++) {
      var _monitor = _active[_i];
      // A monitor can be removed by another monitor during an event.
      // We need to check that the monitor is still registered before calling it
      if (dragging.active.has(_monitor)) {
        var _monitor$eventName;
        // @ts-expect-error: I cannot get this type working!
        (_monitor$eventName = _monitor[eventName]) === null || _monitor$eventName === void 0 || _monitor$eventName.call(_monitor, payload);
      }
    }
    if (eventName === 'onDrop') {
      dragging.active.clear();
      dragging = null;
    }
  }
  return {
    dispatchEvent: dispatchEvent,
    monitorForConsumers: monitorForConsumers
  };
}

/***/ }),

/***/ "./node_modules/@atlaskit/pragmatic-drag-and-drop/dist/esm/public-utils/combine.js":
/*!*****************************************************************************************!*\
  !*** ./node_modules/@atlaskit/pragmatic-drag-and-drop/dist/esm/public-utils/combine.js ***!
  \*****************************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   combine: () => (/* binding */ combine)
/* harmony export */ });
/** Create a new combined function that will call all the provided functions */
function combine() {
  for (var _len = arguments.length, fns = new Array(_len), _key = 0; _key < _len; _key++) {
    fns[_key] = arguments[_key];
  }
  return function cleanup() {
    fns.forEach(function (fn) {
      return fn();
    });
  };
}

/***/ }),

/***/ "./node_modules/@atlaskit/pragmatic-drag-and-drop/dist/esm/public-utils/once.js":
/*!**************************************************************************************!*\
  !*** ./node_modules/@atlaskit/pragmatic-drag-and-drop/dist/esm/public-utils/once.js ***!
  \**************************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   once: () => (/* binding */ once)
/* harmony export */ });
/** Provide a function that you only ever want to be called a single time */
function once(fn) {
  var cache = null;
  return function wrapped() {
    if (!cache) {
      for (var _len = arguments.length, args = new Array(_len), _key = 0; _key < _len; _key++) {
        args[_key] = arguments[_key];
      }
      var result = fn.apply(this, args);
      cache = {
        result: result
      };
    }
    return cache.result;
  };
}

/***/ }),

/***/ "./node_modules/@atlaskit/pragmatic-drag-and-drop/dist/esm/util/add-attribute.js":
/*!***************************************************************************************!*\
  !*** ./node_modules/@atlaskit/pragmatic-drag-and-drop/dist/esm/util/add-attribute.js ***!
  \***************************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   addAttribute: () => (/* binding */ addAttribute)
/* harmony export */ });
function addAttribute(element, _ref) {
  var attribute = _ref.attribute,
    value = _ref.value;
  element.setAttribute(attribute, value);
  return function () {
    return element.removeAttribute(attribute);
  };
}

/***/ }),

/***/ "./node_modules/@atlaskit/pragmatic-drag-and-drop/dist/esm/util/android.js":
/*!*********************************************************************************!*\
  !*** ./node_modules/@atlaskit/pragmatic-drag-and-drop/dist/esm/util/android.js ***!
  \*********************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   androidFallbackText: () => (/* binding */ androidFallbackText),
/* harmony export */   isAndroid: () => (/* binding */ isAndroid)
/* harmony export */ });
/* harmony import */ var _public_utils_once__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ../public-utils/once */ "./node_modules/@atlaskit/pragmatic-drag-and-drop/dist/esm/public-utils/once.js");


// Using `once` as the value won't change in a browser

var isAndroid = (0,_public_utils_once__WEBPACK_IMPORTED_MODULE_0__.once)(function isAndroid() {
  return navigator.userAgent.toLocaleLowerCase().includes('android');
});
var androidFallbackText = 'pdnd:android-fallback';

/***/ }),

/***/ "./node_modules/@atlaskit/pragmatic-drag-and-drop/dist/esm/util/changing-window/count-events-for-safari.js":
/*!*****************************************************************************************************************!*\
  !*** ./node_modules/@atlaskit/pragmatic-drag-and-drop/dist/esm/util/changing-window/count-events-for-safari.js ***!
  \*****************************************************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   isEnteringWindowInSafari: () => (/* binding */ isEnteringWindowInSafari),
/* harmony export */   isLeavingWindowInSafari: () => (/* binding */ isLeavingWindowInSafari)
/* harmony export */ });
/* harmony import */ var bind_event_listener__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! bind-event-listener */ "./node_modules/bind-event-listener/dist/index.js");
/* harmony import */ var _is_safari__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ../is-safari */ "./node_modules/@atlaskit/pragmatic-drag-and-drop/dist/esm/util/is-safari.js");



/* For "dragenter" events, the browser should set `relatedTarget` to the previous element.
 * For external drag operations, our first "dragenter" event should have a `event.relatedTarget` of `null`.
 *
 *  Unfortunately in Safari `event.relatedTarget` is *always* set to `null`
 *  Safari bug: https://bugs.webkit.org/show_bug.cgi?id=242627
 *  To work around this we count "dragenter" and "dragleave" events */

// Using symbols for event properties so we don't clash with
// anything on the `event` object
var symbols = {
  isLeavingWindow: Symbol('leaving'),
  isEnteringWindow: Symbol('entering')
};
function isEnteringWindowInSafari(_ref) {
  var dragEnter = _ref.dragEnter;
  if (!(0,_is_safari__WEBPACK_IMPORTED_MODULE_1__.isSafari)()) {
    return false;
  }
  return dragEnter.hasOwnProperty(symbols.isEnteringWindow);
}
function isLeavingWindowInSafari(_ref2) {
  var dragLeave = _ref2.dragLeave;
  if (!(0,_is_safari__WEBPACK_IMPORTED_MODULE_1__.isSafari)()) {
    return false;
  }
  return dragLeave.hasOwnProperty(symbols.isLeavingWindow);
}
(function fixSafari() {
  // Don't do anything when server side rendering
  if (typeof window === 'undefined') {
    return;
  }

  // rather than checking the userAgent for "jsdom" we can do this check
  // so that the check will be removed completely in production code
  if (false) // removed by dead control flow
{}
  if (!(0,_is_safari__WEBPACK_IMPORTED_MODULE_1__.isSafari)()) {
    return;
  }
  function getInitialState() {
    return {
      enterCount: 0,
      isOverWindow: false
    };
  }
  var state = getInitialState();
  function resetState() {
    state = getInitialState();
  }

  // These event listeners are bound _forever_ and _never_ removed
  // We don't bother cleaning up these event listeners (for now)
  // as this workaround is only for Safari

  // This is how the event count works:
  //
  // lift (+1 enterCount)
  // - dragstart(draggable) [enterCount: 0]
  // - dragenter(draggable) [enterCount: 1]
  // leaving draggable (+0 enterCount)
  // - dragenter(document.body) [enterCount: 2]
  // - dragleave(draggable) [enterCount: 1]
  // leaving window (-1 enterCount)
  // - dragleave(document.body) [enterCount: 0] {leaving the window}

  // Things to note:
  // - dragenter and dragleave bubble
  // - the first dragenter when entering a window might not be on `window`
  //   - it could be on an element that is pressed up against the window
  //   - (so we cannot rely on `event.target` values)

  (0,bind_event_listener__WEBPACK_IMPORTED_MODULE_0__.bindAll)(window, [{
    type: 'dragstart',
    listener: function listener() {
      state.enterCount = 0;
      // drag start occurs in the source window
      state.isOverWindow = true;
      // When a drag first starts it will also trigger a "dragenter" on the draggable element
    }
  }, {
    type: 'drop',
    listener: resetState
  }, {
    type: 'dragend',
    listener: resetState
  }, {
    type: 'dragenter',
    listener: function listener(event) {
      if (!state.isOverWindow && state.enterCount === 0) {
        // Patching the `event` object
        // The `event` object is shared with all event listeners for the event
        event[symbols.isEnteringWindow] = true;
      }
      state.isOverWindow = true;
      state.enterCount++;
    }
  }, {
    type: 'dragleave',
    listener: function listener(event) {
      state.enterCount--;
      if (state.isOverWindow && state.enterCount === 0) {
        // Patching the `event` object as it is shared with all event listeners
        // The `event` object is shared with all event listeners for the event
        event[symbols.isLeavingWindow] = true;
        state.isOverWindow = false;
      }
    }
  }],
  // using `capture: true` so that adding event listeners
  // in bubble phase will have the correct symbols
  {
    capture: true
  });
})();

/***/ }),

/***/ "./node_modules/@atlaskit/pragmatic-drag-and-drop/dist/esm/util/changing-window/is-from-another-window.js":
/*!****************************************************************************************************************!*\
  !*** ./node_modules/@atlaskit/pragmatic-drag-and-drop/dist/esm/util/changing-window/is-from-another-window.js ***!
  \****************************************************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   isFromAnotherWindow: () => (/* binding */ isFromAnotherWindow)
/* harmony export */ });
/**
 * Does the `EventTarget` look like a `Node` based on "duck typing".
 *
 * Helpful when the `Node` might be outside of the current document
 * so we cannot to an `target instanceof Node` check.
 */
function isNodeLike(target) {
  return 'nodeName' in target;
}

/**
 * Is an `EventTarget` a `Node` from another `window`?
 */
function isFromAnotherWindow(eventTarget) {
  return isNodeLike(eventTarget) && eventTarget.ownerDocument !== document;
}

/***/ }),

/***/ "./node_modules/@atlaskit/pragmatic-drag-and-drop/dist/esm/util/changing-window/is-leaving-window.js":
/*!***********************************************************************************************************!*\
  !*** ./node_modules/@atlaskit/pragmatic-drag-and-drop/dist/esm/util/changing-window/is-leaving-window.js ***!
  \***********************************************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   isLeavingWindow: () => (/* binding */ isLeavingWindow)
/* harmony export */ });
/* harmony import */ var _is_firefox__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ../is-firefox */ "./node_modules/@atlaskit/pragmatic-drag-and-drop/dist/esm/util/is-firefox.js");
/* harmony import */ var _is_safari__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ../is-safari */ "./node_modules/@atlaskit/pragmatic-drag-and-drop/dist/esm/util/is-safari.js");
/* harmony import */ var _count_events_for_safari__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./count-events-for-safari */ "./node_modules/@atlaskit/pragmatic-drag-and-drop/dist/esm/util/changing-window/count-events-for-safari.js");
/* harmony import */ var _is_from_another_window__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ./is-from-another-window */ "./node_modules/@atlaskit/pragmatic-drag-and-drop/dist/esm/util/changing-window/is-from-another-window.js");




function isLeavingWindow(_ref) {
  var dragLeave = _ref.dragLeave;
  var type = dragLeave.type,
    relatedTarget = dragLeave.relatedTarget;
  if (type !== 'dragleave') {
    return false;
  }
  if ((0,_is_safari__WEBPACK_IMPORTED_MODULE_1__.isSafari)()) {
    return (0,_count_events_for_safari__WEBPACK_IMPORTED_MODULE_2__.isLeavingWindowInSafari)({
      dragLeave: dragLeave
    });
  }

  // Standard check: if going to `null` we are leaving the `window`
  if (relatedTarget == null) {
    return true;
  }

  /**
   * 🦊 Exception: `iframe` in Firefox (`125.0`)
   *
   * Case 1: parent `window` → child `iframe`
   * `dragLeave.relatedTarget` is element _inside_ the child `iframe`
   * (foreign element)
   *
   * Case 2: child `iframe` → parent `window`
   * `dragLeave.relatedTarget` is the `iframe` in the parent `window`
   * (foreign element)
   */

  if ((0,_is_firefox__WEBPACK_IMPORTED_MODULE_0__.isFirefox)()) {
    return (0,_is_from_another_window__WEBPACK_IMPORTED_MODULE_3__.isFromAnotherWindow)(relatedTarget);
  }

  /**
   * 🌏 Exception: `iframe` in Chrome (`124.0`)
   *
   * Case 1: parent `window` → child `iframe`
   * `dragLeave.relatedTarget` is the `iframe` in the parent `window`
   *
   * Case 2: child `iframe` → parent `window`
   * `dragLeave.relatedTarget` is `null` *(standard check)*
   */

  // Case 2
  // Using `instanceof` check as the element will be in the same `window`
  return relatedTarget instanceof HTMLIFrameElement;
}

/***/ }),

/***/ "./node_modules/@atlaskit/pragmatic-drag-and-drop/dist/esm/util/detect-broken-drag.js":
/*!********************************************************************************************!*\
  !*** ./node_modules/@atlaskit/pragmatic-drag-and-drop/dist/esm/util/detect-broken-drag.js ***!
  \********************************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   getBindingsForBrokenDrags: () => (/* binding */ getBindingsForBrokenDrags)
/* harmony export */ });
function getBindingsForBrokenDrags(_ref) {
  var onDragEnd = _ref.onDragEnd;
  return [
  // ## Detecting drag ending for removed draggables
  //
  // If a draggable element is removed during a drag and the user drops:
  // 1. if over a valid drop target: we get a "drop" event to know the drag is finished
  // 2. if not over a valid drop target (or cancelled): we get nothing
  // The "dragend" event will not fire on the source draggable if it has been
  // removed from the DOM.
  // So we need to figure out if a drag operation has finished by looking at other events
  // We can do this by looking at other events

  // ### First detection: "pointermove" events

  // 1. "pointermove" events cannot fire during a drag and drop operation
  // according to the spec. So if we get a "pointermove" it means that
  // the drag and drop operations has finished. So if we get a "pointermove"
  // we know that the drag is over
  // 2. 🦊😤 Drag and drop operations are _supposed_ to suppress
  // other pointer events. However, firefox will allow a few
  // pointer event to get through after a drag starts.
  // The most I've seen is 3
  {
    type: 'pointermove',
    listener: function () {
      var callCount = 0;
      return function listener() {
        // Using 20 as it is far bigger than the most observed (3)
        if (callCount < 20) {
          callCount++;
          return;
        }
        onDragEnd();
      };
    }()
  },
  // ### Second detection: "pointerdown" events

  // If we receive this event then we know that a drag operation has finished
  // and potentially another one is about to start.
  // Note: `pointerdown` fires on all browsers / platforms before "dragstart"
  {
    type: 'pointerdown',
    listener: onDragEnd
  }];
}

/***/ }),

/***/ "./node_modules/@atlaskit/pragmatic-drag-and-drop/dist/esm/util/get-input.js":
/*!***********************************************************************************!*\
  !*** ./node_modules/@atlaskit/pragmatic-drag-and-drop/dist/esm/util/get-input.js ***!
  \***********************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   getInput: () => (/* binding */ getInput)
/* harmony export */ });
function getInput(event) {
  return {
    altKey: event.altKey,
    button: event.button,
    buttons: event.buttons,
    ctrlKey: event.ctrlKey,
    metaKey: event.metaKey,
    shiftKey: event.shiftKey,
    clientX: event.clientX,
    clientY: event.clientY,
    pageX: event.pageX,
    pageY: event.pageY
  };
}

/***/ }),

/***/ "./node_modules/@atlaskit/pragmatic-drag-and-drop/dist/esm/util/is-firefox.js":
/*!************************************************************************************!*\
  !*** ./node_modules/@atlaskit/pragmatic-drag-and-drop/dist/esm/util/is-firefox.js ***!
  \************************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   isFirefox: () => (/* binding */ isFirefox)
/* harmony export */ });
/* harmony import */ var _public_utils_once__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ../public-utils/once */ "./node_modules/@atlaskit/pragmatic-drag-and-drop/dist/esm/public-utils/once.js");


// using `cache` as our `isFirefox()` result will not change in a browser

/**
 * Returns `true` if a `Firefox` browser
 * */
var isFirefox = (0,_public_utils_once__WEBPACK_IMPORTED_MODULE_0__.once)(function isFirefox() {
  if (false) // removed by dead control flow
{}
  return navigator.userAgent.includes('Firefox');
});

/***/ }),

/***/ "./node_modules/@atlaskit/pragmatic-drag-and-drop/dist/esm/util/is-safari.js":
/*!***********************************************************************************!*\
  !*** ./node_modules/@atlaskit/pragmatic-drag-and-drop/dist/esm/util/is-safari.js ***!
  \***********************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   isSafari: () => (/* binding */ isSafari)
/* harmony export */ });
/* harmony import */ var _public_utils_once__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ../public-utils/once */ "./node_modules/@atlaskit/pragmatic-drag-and-drop/dist/esm/public-utils/once.js");


// using `cache` as our `isSafari()` result will not change in a browser

/**
 * Returns `true` if a `Safari` browser.
 * Returns `true` if the browser is running on iOS (they are all Safari).
 *
 * Use `isSafariOnIOS` if you want to check if something is Safari + iOS
 * */
var isSafari = (0,_public_utils_once__WEBPACK_IMPORTED_MODULE_0__.once)(function isSafari() {
  if (false) // removed by dead control flow
{}
  var _navigator = navigator,
    userAgent = _navigator.userAgent;
  return userAgent.includes('AppleWebKit') && !userAgent.includes('Chrome');
});

/***/ }),

/***/ "./node_modules/@atlaskit/pragmatic-drag-and-drop/dist/esm/util/max-z-index.js":
/*!*************************************************************************************!*\
  !*** ./node_modules/@atlaskit/pragmatic-drag-and-drop/dist/esm/util/max-z-index.js ***!
  \*************************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   maxZIndex: () => (/* binding */ maxZIndex)
/* harmony export */ });
// Maximum possible z-index
// https://stackoverflow.com/questions/491052/minimum-and-maximum-value-of-z-index
var maxZIndex = 2147483647;

/***/ }),

/***/ "./node_modules/@atlaskit/pragmatic-drag-and-drop/dist/esm/util/media-types/text-media-type.js":
/*!*****************************************************************************************************!*\
  !*** ./node_modules/@atlaskit/pragmatic-drag-and-drop/dist/esm/util/media-types/text-media-type.js ***!
  \*****************************************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   textMediaType: () => (/* binding */ textMediaType)
/* harmony export */ });
// Why we put the media types in their own files:
//
// - we are not putting them all in one file as not all adapters need all types
// - we are not putting them in the external helpers as some things just need the
//   types and not the external functions code
var textMediaType = 'text/plain';

/***/ }),

/***/ "./node_modules/@atlaskit/pragmatic-drag-and-drop/dist/esm/util/media-types/url-media-type.js":
/*!****************************************************************************************************!*\
  !*** ./node_modules/@atlaskit/pragmatic-drag-and-drop/dist/esm/util/media-types/url-media-type.js ***!
  \****************************************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   URLMediaType: () => (/* binding */ URLMediaType)
/* harmony export */ });
// Why we put the media types in their own files:
//
// - we are not putting them all in one file as not all adapters need all types
// - we are not putting them in the external helpers as some things just need the
//   types and not the external functions code
var URLMediaType = 'text/uri-list';

/***/ }),

/***/ "./node_modules/@babel/runtime/helpers/esm/arrayLikeToArray.js":
/*!*********************************************************************!*\
  !*** ./node_modules/@babel/runtime/helpers/esm/arrayLikeToArray.js ***!
  \*********************************************************************/
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ _arrayLikeToArray)
/* harmony export */ });
function _arrayLikeToArray(r, a) {
  (null == a || a > r.length) && (a = r.length);
  for (var e = 0, n = Array(a); e < a; e++) n[e] = r[e];
  return n;
}


/***/ }),

/***/ "./node_modules/@babel/runtime/helpers/esm/arrayWithHoles.js":
/*!*******************************************************************!*\
  !*** ./node_modules/@babel/runtime/helpers/esm/arrayWithHoles.js ***!
  \*******************************************************************/
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ _arrayWithHoles)
/* harmony export */ });
function _arrayWithHoles(r) {
  if (Array.isArray(r)) return r;
}


/***/ }),

/***/ "./node_modules/@babel/runtime/helpers/esm/arrayWithoutHoles.js":
/*!**********************************************************************!*\
  !*** ./node_modules/@babel/runtime/helpers/esm/arrayWithoutHoles.js ***!
  \**********************************************************************/
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ _arrayWithoutHoles)
/* harmony export */ });
/* harmony import */ var _arrayLikeToArray_js__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./arrayLikeToArray.js */ "./node_modules/@babel/runtime/helpers/esm/arrayLikeToArray.js");

function _arrayWithoutHoles(r) {
  if (Array.isArray(r)) return (0,_arrayLikeToArray_js__WEBPACK_IMPORTED_MODULE_0__["default"])(r);
}


/***/ }),

/***/ "./node_modules/@babel/runtime/helpers/esm/defineProperty.js":
/*!*******************************************************************!*\
  !*** ./node_modules/@babel/runtime/helpers/esm/defineProperty.js ***!
  \*******************************************************************/
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ _defineProperty)
/* harmony export */ });
/* harmony import */ var _toPropertyKey_js__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./toPropertyKey.js */ "./node_modules/@babel/runtime/helpers/esm/toPropertyKey.js");

function _defineProperty(e, r, t) {
  return (r = (0,_toPropertyKey_js__WEBPACK_IMPORTED_MODULE_0__["default"])(r)) in e ? Object.defineProperty(e, r, {
    value: t,
    enumerable: !0,
    configurable: !0,
    writable: !0
  }) : e[r] = t, e;
}


/***/ }),

/***/ "./node_modules/@babel/runtime/helpers/esm/iterableToArray.js":
/*!********************************************************************!*\
  !*** ./node_modules/@babel/runtime/helpers/esm/iterableToArray.js ***!
  \********************************************************************/
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ _iterableToArray)
/* harmony export */ });
function _iterableToArray(r) {
  if ("undefined" != typeof Symbol && null != r[Symbol.iterator] || null != r["@@iterator"]) return Array.from(r);
}


/***/ }),

/***/ "./node_modules/@babel/runtime/helpers/esm/iterableToArrayLimit.js":
/*!*************************************************************************!*\
  !*** ./node_modules/@babel/runtime/helpers/esm/iterableToArrayLimit.js ***!
  \*************************************************************************/
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ _iterableToArrayLimit)
/* harmony export */ });
function _iterableToArrayLimit(r, l) {
  var t = null == r ? null : "undefined" != typeof Symbol && r[Symbol.iterator] || r["@@iterator"];
  if (null != t) {
    var e,
      n,
      i,
      u,
      a = [],
      f = !0,
      o = !1;
    try {
      if (i = (t = t.call(r)).next, 0 === l) {
        if (Object(t) !== t) return;
        f = !1;
      } else for (; !(f = (e = i.call(t)).done) && (a.push(e.value), a.length !== l); f = !0);
    } catch (r) {
      o = !0, n = r;
    } finally {
      try {
        if (!f && null != t["return"] && (u = t["return"](), Object(u) !== u)) return;
      } finally {
        if (o) throw n;
      }
    }
    return a;
  }
}


/***/ }),

/***/ "./node_modules/@babel/runtime/helpers/esm/nonIterableRest.js":
/*!********************************************************************!*\
  !*** ./node_modules/@babel/runtime/helpers/esm/nonIterableRest.js ***!
  \********************************************************************/
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ _nonIterableRest)
/* harmony export */ });
function _nonIterableRest() {
  throw new TypeError("Invalid attempt to destructure non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method.");
}


/***/ }),

/***/ "./node_modules/@babel/runtime/helpers/esm/nonIterableSpread.js":
/*!**********************************************************************!*\
  !*** ./node_modules/@babel/runtime/helpers/esm/nonIterableSpread.js ***!
  \**********************************************************************/
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ _nonIterableSpread)
/* harmony export */ });
function _nonIterableSpread() {
  throw new TypeError("Invalid attempt to spread non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method.");
}


/***/ }),

/***/ "./node_modules/@babel/runtime/helpers/esm/slicedToArray.js":
/*!******************************************************************!*\
  !*** ./node_modules/@babel/runtime/helpers/esm/slicedToArray.js ***!
  \******************************************************************/
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ _slicedToArray)
/* harmony export */ });
/* harmony import */ var _arrayWithHoles_js__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./arrayWithHoles.js */ "./node_modules/@babel/runtime/helpers/esm/arrayWithHoles.js");
/* harmony import */ var _iterableToArrayLimit_js__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./iterableToArrayLimit.js */ "./node_modules/@babel/runtime/helpers/esm/iterableToArrayLimit.js");
/* harmony import */ var _unsupportedIterableToArray_js__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./unsupportedIterableToArray.js */ "./node_modules/@babel/runtime/helpers/esm/unsupportedIterableToArray.js");
/* harmony import */ var _nonIterableRest_js__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ./nonIterableRest.js */ "./node_modules/@babel/runtime/helpers/esm/nonIterableRest.js");




function _slicedToArray(r, e) {
  return (0,_arrayWithHoles_js__WEBPACK_IMPORTED_MODULE_0__["default"])(r) || (0,_iterableToArrayLimit_js__WEBPACK_IMPORTED_MODULE_1__["default"])(r, e) || (0,_unsupportedIterableToArray_js__WEBPACK_IMPORTED_MODULE_2__["default"])(r, e) || (0,_nonIterableRest_js__WEBPACK_IMPORTED_MODULE_3__["default"])();
}


/***/ }),

/***/ "./node_modules/@babel/runtime/helpers/esm/toConsumableArray.js":
/*!**********************************************************************!*\
  !*** ./node_modules/@babel/runtime/helpers/esm/toConsumableArray.js ***!
  \**********************************************************************/
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ _toConsumableArray)
/* harmony export */ });
/* harmony import */ var _arrayWithoutHoles_js__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./arrayWithoutHoles.js */ "./node_modules/@babel/runtime/helpers/esm/arrayWithoutHoles.js");
/* harmony import */ var _iterableToArray_js__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./iterableToArray.js */ "./node_modules/@babel/runtime/helpers/esm/iterableToArray.js");
/* harmony import */ var _unsupportedIterableToArray_js__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./unsupportedIterableToArray.js */ "./node_modules/@babel/runtime/helpers/esm/unsupportedIterableToArray.js");
/* harmony import */ var _nonIterableSpread_js__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ./nonIterableSpread.js */ "./node_modules/@babel/runtime/helpers/esm/nonIterableSpread.js");




function _toConsumableArray(r) {
  return (0,_arrayWithoutHoles_js__WEBPACK_IMPORTED_MODULE_0__["default"])(r) || (0,_iterableToArray_js__WEBPACK_IMPORTED_MODULE_1__["default"])(r) || (0,_unsupportedIterableToArray_js__WEBPACK_IMPORTED_MODULE_2__["default"])(r) || (0,_nonIterableSpread_js__WEBPACK_IMPORTED_MODULE_3__["default"])();
}


/***/ }),

/***/ "./node_modules/@babel/runtime/helpers/esm/toPrimitive.js":
/*!****************************************************************!*\
  !*** ./node_modules/@babel/runtime/helpers/esm/toPrimitive.js ***!
  \****************************************************************/
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ toPrimitive)
/* harmony export */ });
/* harmony import */ var _typeof_js__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./typeof.js */ "./node_modules/@babel/runtime/helpers/esm/typeof.js");

function toPrimitive(t, r) {
  if ("object" != (0,_typeof_js__WEBPACK_IMPORTED_MODULE_0__["default"])(t) || !t) return t;
  var e = t[Symbol.toPrimitive];
  if (void 0 !== e) {
    var i = e.call(t, r || "default");
    if ("object" != (0,_typeof_js__WEBPACK_IMPORTED_MODULE_0__["default"])(i)) return i;
    throw new TypeError("@@toPrimitive must return a primitive value.");
  }
  return ("string" === r ? String : Number)(t);
}


/***/ }),

/***/ "./node_modules/@babel/runtime/helpers/esm/toPropertyKey.js":
/*!******************************************************************!*\
  !*** ./node_modules/@babel/runtime/helpers/esm/toPropertyKey.js ***!
  \******************************************************************/
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ toPropertyKey)
/* harmony export */ });
/* harmony import */ var _typeof_js__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./typeof.js */ "./node_modules/@babel/runtime/helpers/esm/typeof.js");
/* harmony import */ var _toPrimitive_js__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./toPrimitive.js */ "./node_modules/@babel/runtime/helpers/esm/toPrimitive.js");


function toPropertyKey(t) {
  var i = (0,_toPrimitive_js__WEBPACK_IMPORTED_MODULE_1__["default"])(t, "string");
  return "symbol" == (0,_typeof_js__WEBPACK_IMPORTED_MODULE_0__["default"])(i) ? i : i + "";
}


/***/ }),

/***/ "./node_modules/@babel/runtime/helpers/esm/typeof.js":
/*!***********************************************************!*\
  !*** ./node_modules/@babel/runtime/helpers/esm/typeof.js ***!
  \***********************************************************/
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ _typeof)
/* harmony export */ });
function _typeof(o) {
  "@babel/helpers - typeof";

  return _typeof = "function" == typeof Symbol && "symbol" == typeof Symbol.iterator ? function (o) {
    return typeof o;
  } : function (o) {
    return o && "function" == typeof Symbol && o.constructor === Symbol && o !== Symbol.prototype ? "symbol" : typeof o;
  }, _typeof(o);
}


/***/ }),

/***/ "./node_modules/@babel/runtime/helpers/esm/unsupportedIterableToArray.js":
/*!*******************************************************************************!*\
  !*** ./node_modules/@babel/runtime/helpers/esm/unsupportedIterableToArray.js ***!
  \*******************************************************************************/
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ _unsupportedIterableToArray)
/* harmony export */ });
/* harmony import */ var _arrayLikeToArray_js__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./arrayLikeToArray.js */ "./node_modules/@babel/runtime/helpers/esm/arrayLikeToArray.js");

function _unsupportedIterableToArray(r, a) {
  if (r) {
    if ("string" == typeof r) return (0,_arrayLikeToArray_js__WEBPACK_IMPORTED_MODULE_0__["default"])(r, a);
    var t = {}.toString.call(r).slice(8, -1);
    return "Object" === t && r.constructor && (t = r.constructor.name), "Map" === t || "Set" === t ? Array.from(r) : "Arguments" === t || /^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(t) ? (0,_arrayLikeToArray_js__WEBPACK_IMPORTED_MODULE_0__["default"])(r, a) : void 0;
  }
}


/***/ }),

/***/ "./node_modules/bind-event-listener/dist/bind-all.js":
/*!***********************************************************!*\
  !*** ./node_modules/bind-event-listener/dist/bind-all.js ***!
  \***********************************************************/
/***/ (function(__unused_webpack_module, exports, __webpack_require__) {


var __assign = (this && this.__assign) || function () {
    __assign = Object.assign || function(t) {
        for (var s, i = 1, n = arguments.length; i < n; i++) {
            s = arguments[i];
            for (var p in s) if (Object.prototype.hasOwnProperty.call(s, p))
                t[p] = s[p];
        }
        return t;
    };
    return __assign.apply(this, arguments);
};
Object.defineProperty(exports, "__esModule", ({ value: true }));
exports.bindAll = void 0;
var bind_1 = __webpack_require__(/*! ./bind */ "./node_modules/bind-event-listener/dist/bind.js");
function toOptions(value) {
    if (typeof value === 'undefined') {
        return undefined;
    }
    if (typeof value === 'boolean') {
        return {
            capture: value,
        };
    }
    return value;
}
function getBinding(original, sharedOptions) {
    if (sharedOptions == null) {
        return original;
    }
    var binding = __assign(__assign({}, original), { options: __assign(__assign({}, toOptions(sharedOptions)), toOptions(original.options)) });
    return binding;
}
function bindAll(target, bindings, sharedOptions) {
    var unbinds = bindings.map(function (original) {
        var binding = getBinding(original, sharedOptions);
        return (0, bind_1.bind)(target, binding);
    });
    return function unbindAll() {
        unbinds.forEach(function (unbind) { return unbind(); });
    };
}
exports.bindAll = bindAll;


/***/ }),

/***/ "./node_modules/bind-event-listener/dist/bind.js":
/*!*******************************************************!*\
  !*** ./node_modules/bind-event-listener/dist/bind.js ***!
  \*******************************************************/
/***/ ((__unused_webpack_module, exports) => {


Object.defineProperty(exports, "__esModule", ({ value: true }));
exports.bind = void 0;
function bind(target, _a) {
    var type = _a.type, listener = _a.listener, options = _a.options;
    target.addEventListener(type, listener, options);
    return function unbind() {
        target.removeEventListener(type, listener, options);
    };
}
exports.bind = bind;


/***/ }),

/***/ "./node_modules/bind-event-listener/dist/index.js":
/*!********************************************************!*\
  !*** ./node_modules/bind-event-listener/dist/index.js ***!
  \********************************************************/
/***/ ((__unused_webpack_module, exports, __webpack_require__) => {


Object.defineProperty(exports, "__esModule", ({ value: true }));
exports.bindAll = exports.bind = void 0;
var bind_1 = __webpack_require__(/*! ./bind */ "./node_modules/bind-event-listener/dist/bind.js");
Object.defineProperty(exports, "bind", ({ enumerable: true, get: function () { return bind_1.bind; } }));
var bind_all_1 = __webpack_require__(/*! ./bind-all */ "./node_modules/bind-event-listener/dist/bind-all.js");
Object.defineProperty(exports, "bindAll", ({ enumerable: true, get: function () { return bind_all_1.bindAll; } }));


/***/ }),

/***/ "./node_modules/raf-schd/dist/raf-schd.esm.js":
/*!****************************************************!*\
  !*** ./node_modules/raf-schd/dist/raf-schd.esm.js ***!
  \****************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
var rafSchd = function rafSchd(fn) {
  var lastArgs = [];
  var frameId = null;

  var wrapperFn = function wrapperFn() {
    for (var _len = arguments.length, args = new Array(_len), _key = 0; _key < _len; _key++) {
      args[_key] = arguments[_key];
    }

    lastArgs = args;

    if (frameId) {
      return;
    }

    frameId = requestAnimationFrame(function () {
      frameId = null;
      fn.apply(void 0, lastArgs);
    });
  };

  wrapperFn.cancel = function () {
    if (!frameId) {
      return;
    }

    cancelAnimationFrame(frameId);
    frameId = null;
  };

  return wrapperFn;
};

/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (rafSchd);


/***/ }),

/***/ "./node_modules/react-dom/client.js":
/*!******************************************!*\
  !*** ./node_modules/react-dom/client.js ***!
  \******************************************/
/***/ ((__unused_webpack_module, exports, __webpack_require__) => {



var m = __webpack_require__(/*! react-dom */ "react-dom");
if (false) // removed by dead control flow
{} else {
  var i = m.__SECRET_INTERNALS_DO_NOT_USE_OR_YOU_WILL_BE_FIRED;
  exports.createRoot = function(c, o) {
    i.usingClientEntryPoint = true;
    try {
      return m.createRoot(c, o);
    } finally {
      i.usingClientEntryPoint = false;
    }
  };
  exports.hydrateRoot = function(c, h, o) {
    i.usingClientEntryPoint = true;
    try {
      return m.hydrateRoot(c, h, o);
    } finally {
      i.usingClientEntryPoint = false;
    }
  };
}


/***/ }),

/***/ "./node_modules/tiny-invariant/dist/esm/tiny-invariant.js":
/*!****************************************************************!*\
  !*** ./node_modules/tiny-invariant/dist/esm/tiny-invariant.js ***!
  \****************************************************************/
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ invariant)
/* harmony export */ });
var isProduction = "development" === 'production';
var prefix = 'Invariant failed';
function invariant(condition, message) {
    if (condition) {
        return;
    }
    if (isProduction) {
        throw new Error(prefix);
    }
    var provided = typeof message === 'function' ? message() : message;
    var value = provided ? "".concat(prefix, ": ").concat(provided) : prefix;
    throw new Error(value);
}




/***/ }),

/***/ "./utils/api.ts":
/*!**********************!*\
  !*** ./utils/api.ts ***!
  \**********************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   fetchStatus: () => (/* binding */ fetchStatus),
/* harmony export */   fetchTasks: () => (/* binding */ fetchTasks),
/* harmony export */   updateTask: () => (/* binding */ updateTask)
/* harmony export */ });
var __awaiter = (undefined && undefined.__awaiter) || function (thisArg, _arguments, P, generator) {
    function adopt(value) { return value instanceof P ? value : new P(function (resolve) { resolve(value); }); }
    return new (P || (P = Promise))(function (resolve, reject) {
        function fulfilled(value) { try { step(generator.next(value)); } catch (e) { reject(e); } }
        function rejected(value) { try { step(generator["throw"](value)); } catch (e) { reject(e); } }
        function step(result) { result.done ? resolve(result.value) : adopt(result.value).then(fulfilled, rejected); }
        step((generator = generator.apply(thisArg, _arguments || [])).next());
    });
};
var _a, _b;
const apiRoot = (_a = window.WPO_AOM_TaskManager) === null || _a === void 0 ? void 0 : _a.apiRoot;
const nonce = (_b = window.WPO_AOM_TaskManager) === null || _b === void 0 ? void 0 : _b.nonce;
if (!apiRoot) {
    console.warn("⚠️ API Root is missing. API calls will fail.");
}
/**
 * Handles the API response, checking for errors and parsing JSON.
 *
 * @template T - The expected type of the response data.
 * @param response
 * @returns {Promise<T>} The parsed JSON data.
 * @throws Will throw an error if the response is not ok.
 */
function handleResponse(response) {
    return __awaiter(this, void 0, void 0, function* () {
        if (!response.ok) {
            const errorText = yield response.text();
            throw new Error(`API request failed: ${response.status}: ${errorText}`);
        }
        return response.json();
    });
}
/**
 * Fetches tasks from the API and maps custom fields to task properties.
 *
 * @returns {Promise<Task[]>} A promise that resolves to an array of tasks.
 * @throws Will throw an error if the API request fails.
 */
function fetchTasks() {
    return __awaiter(this, void 0, void 0, function* () {
        const response = yield fetch(`${apiRoot}/tasks`, {
            method: 'GET',
            credentials: 'include',
            headers: { 'X-WP-Nonce': nonce },
        });
        const data = yield handleResponse(response);
        return data.map((task) => {
            var _a, _b, _c, _d, _e, _f;
            const statusField = task.fields.find((field) => field.slug === 'status');
            const positionField = task.fields.find((field) => field.slug === 'status_position');
            return Object.assign(Object.assign({}, task), { column: (_c = (_b = (_a = statusField === null || statusField === void 0 ? void 0 : statusField.values) === null || _a === void 0 ? void 0 : _a[0]) === null || _b === void 0 ? void 0 : _b.raw) !== null && _c !== void 0 ? _c : undefined, position: (_f = (_e = (_d = positionField === null || positionField === void 0 ? void 0 : positionField.values) === null || _d === void 0 ? void 0 : _d[0]) === null || _e === void 0 ? void 0 : _e.raw) !== null && _f !== void 0 ? _f : undefined });
        });
    });
}
// ToDo: Update this function
function updateTask(taskId, payload) {
    return __awaiter(this, void 0, void 0, function* () {
        const response = yield fetch(`${apiRoot}/tasks/${taskId}`, {
            method: "PUT",
            credentials: "include",
            headers: {
                "Content-Type": "application/json",
                "X-WP-Nonce": nonce,
            },
            body: JSON.stringify(payload),
        });
        return handleResponse(response);
    });
}
/**
 * Fetches the available status options from the API.
 *
 * @returns {Promise<FieldOption[]>} A promise that resolves to an array of column names.
 * @throws Will throw an error if the API request fails.
 */
function fetchStatus() {
    return __awaiter(this, void 0, void 0, function* () {
        const response = yield fetch(`${apiRoot}/tasks/fields/status/options`, {
            method: 'GET',
            credentials: 'include',
            headers: {
                "Content-Type": "application/json",
                "X-WP-Nonce": nonce,
            },
        });
        return handleResponse(response);
    });
}


/***/ }),

/***/ "./views/Calendar/calendar.tsx":
/*!*************************************!*\
  !*** ./views/Calendar/calendar.tsx ***!
  \*************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ Calendar)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);

function Calendar() {
    return (react__WEBPACK_IMPORTED_MODULE_0___default().createElement("div", null, "Calendar View - Coming Soon!"));
}


/***/ }),

/***/ "./views/Kanban/KanbanView.tsx":
/*!*************************************!*\
  !*** ./views/Kanban/KanbanView.tsx ***!
  \*************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   KanbanView: () => (/* binding */ KanbanView)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _context_TaskContext__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ../../context/TaskContext */ "./context/TaskContext.tsx");
/* harmony import */ var _components_Board__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./components/Board */ "./views/Kanban/components/Board.tsx");
var __awaiter = (undefined && undefined.__awaiter) || function (thisArg, _arguments, P, generator) {
    function adopt(value) { return value instanceof P ? value : new P(function (resolve) { resolve(value); }); }
    return new (P || (P = Promise))(function (resolve, reject) {
        function fulfilled(value) { try { step(generator.next(value)); } catch (e) { reject(e); } }
        function rejected(value) { try { step(generator["throw"](value)); } catch (e) { reject(e); } }
        function step(result) { result.done ? resolve(result.value) : adopt(result.value).then(fulfilled, rejected); }
        step((generator = generator.apply(thisArg, _arguments || [])).next());
    });
};



const KanbanView = () => {
    const { loadTasks, loadStatuses } = (0,_context_TaskContext__WEBPACK_IMPORTED_MODULE_1__.useTasks)();
    const [isInitialized, setIsInitialized] = react__WEBPACK_IMPORTED_MODULE_0___default().useState(false);
    const [loading, setLoading] = react__WEBPACK_IMPORTED_MODULE_0___default().useState(false);
    const [error, setError] = react__WEBPACK_IMPORTED_MODULE_0___default().useState(null);
    (0,react__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {
        let isCancelled = false;
        // Initialize by loading tasks and columns.
        const initialize = () => __awaiter(void 0, void 0, void 0, function* () {
            setLoading(true);
            try {
                yield Promise.all([loadTasks(), loadStatuses()]);
                if (!isCancelled)
                    setIsInitialized(true);
            }
            catch (error) {
                if (!isCancelled) {
                    setError(error instanceof Error
                        ? error
                        : new Error("Unknown initialization error"));
                }
            }
            finally {
                if (!isCancelled)
                    setLoading(false);
            }
        });
        if (!isInitialized) {
            console.log("Initializing Kanban View...");
            initialize();
        }
        return () => {
            isCancelled = true;
        };
    }, [isInitialized]);
    if (!isInitialized || loading) {
        return (react__WEBPACK_IMPORTED_MODULE_0___default().createElement("div", { className: "loading-spinner" }, window.WPO_AOM_TaskManager.loading));
    }
    if (error) {
        return (react__WEBPACK_IMPORTED_MODULE_0___default().createElement("div", { className: "error-message" }, window.WPO_AOM_TaskManager.errorLoading));
    }
    return (react__WEBPACK_IMPORTED_MODULE_0___default().createElement(_components_Board__WEBPACK_IMPORTED_MODULE_2__.Board, null));
};


/***/ }),

/***/ "./views/Kanban/components/Board.tsx":
/*!*******************************************!*\
  !*** ./views/Kanban/components/Board.tsx ***!
  \*******************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   Board: () => (/* binding */ Board)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var tiny_invariant__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! tiny-invariant */ "./node_modules/tiny-invariant/dist/esm/tiny-invariant.js");
/* harmony import */ var _atlaskit_pragmatic_drag_and_drop_element_adapter__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @atlaskit/pragmatic-drag-and-drop/element/adapter */ "./node_modules/@atlaskit/pragmatic-drag-and-drop/dist/esm/entry-point/element/adapter.js");
/* harmony import */ var _atlaskit_pragmatic_drag_and_drop_combine__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @atlaskit/pragmatic-drag-and-drop/combine */ "./node_modules/@atlaskit/pragmatic-drag-and-drop/dist/esm/entry-point/combine.js");
/* harmony import */ var _atlaskit_pragmatic_drag_and_drop_auto_scroll_element__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! @atlaskit/pragmatic-drag-and-drop-auto-scroll/element */ "./node_modules/@atlaskit/pragmatic-drag-and-drop-auto-scroll/dist/esm/entry-point/element.js");
/* harmony import */ var _atlaskit_pragmatic_drag_and_drop_hitbox_closest_edge__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! @atlaskit/pragmatic-drag-and-drop-hitbox/closest-edge */ "./node_modules/@atlaskit/pragmatic-drag-and-drop-hitbox/dist/esm/closest-edge.js");
/* harmony import */ var _context_TaskContext__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! ../../../context/TaskContext */ "./context/TaskContext.tsx");
/* harmony import */ var _data__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(/*! ../data */ "./views/Kanban/data.tsx");
/* harmony import */ var _Column__WEBPACK_IMPORTED_MODULE_8__ = __webpack_require__(/*! ./Column */ "./views/Kanban/components/Column.tsx");
var __awaiter = (undefined && undefined.__awaiter) || function (thisArg, _arguments, P, generator) {
    function adopt(value) { return value instanceof P ? value : new P(function (resolve) { resolve(value); }); }
    return new (P || (P = Promise))(function (resolve, reject) {
        function fulfilled(value) { try { step(generator.next(value)); } catch (e) { reject(e); } }
        function rejected(value) { try { step(generator["throw"](value)); } catch (e) { reject(e); } }
        function step(result) { result.done ? resolve(result.value) : adopt(result.value).then(fulfilled, rejected); }
        step((generator = generator.apply(thisArg, _arguments || [])).next());
    });
};









const Board = () => {
    const { tasks, statuses } = (0,_context_TaskContext__WEBPACK_IMPORTED_MODULE_6__.useTasks)();
    const scrollableRef = (0,react__WEBPACK_IMPORTED_MODULE_0__.useRef)(null);
    const { setTasks } = (0,_context_TaskContext__WEBPACK_IMPORTED_MODULE_6__.useTasks)();
    // Group tasks by status(column) name
    const taskGroups = (0,react__WEBPACK_IMPORTED_MODULE_0__.useMemo)(() => {
        const grouped = {};
        statuses.forEach(col => {
            grouped[col.label] = [];
        });
        // Distribute tasks into their respective columns
        tasks.forEach(task => {
            const columnName = task.column;
            if (grouped[columnName]) {
                grouped[columnName].push(task);
            }
        });
        return grouped;
    }, [tasks, statuses]);
    // Enable horizontal auto-scroll while dragging.
    (0,react__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {
        const scrollable = scrollableRef.current;
        (0,tiny_invariant__WEBPACK_IMPORTED_MODULE_1__["default"])(scrollable);
        return (0,_atlaskit_pragmatic_drag_and_drop_combine__WEBPACK_IMPORTED_MODULE_3__.combine)((0,_atlaskit_pragmatic_drag_and_drop_element_adapter__WEBPACK_IMPORTED_MODULE_2__.monitorForElements)({
            canMonitor: ({ source }) => (0,_data__WEBPACK_IMPORTED_MODULE_7__.isCardData)(source.data),
            onDrop(_a) {
                return __awaiter(this, arguments, void 0, function* ({ source, location }) {
                    const dragging = source.data;
                    if (!(0,_data__WEBPACK_IMPORTED_MODULE_7__.isCardData)(dragging))
                        return;
                    const destination = location.current.dropTargets[0];
                    if (!destination) {
                        // if dropped outside any drop targets
                        return;
                    }
                    const dropTargetData = destination.data;
                    const fromColumn = dragging.fromColumn;
                    const task = dragging.task;
                    // Drop on another card
                    if ((0,_data__WEBPACK_IMPORTED_MODULE_7__.isCardDropTargetData)(dropTargetData)) {
                        const toColumn = dropTargetData.column;
                        const edge = (0,_atlaskit_pragmatic_drag_and_drop_hitbox_closest_edge__WEBPACK_IMPORTED_MODULE_5__.extractClosestEdge)(dropTargetData);
                        const targetTask = dropTargetData.task;
                        // Reorder locally
                        setTasks((prev) => {
                            const newTasks = [...prev];
                            // Remove task from source column
                            const fromIndex = newTasks.findIndex(t => t.id === task.id);
                            if (fromIndex === -1)
                                return prev;
                            newTasks.splice(fromIndex, 1);
                            // Find insertion point in target column
                            const targetTaskIndex = newTasks.findIndex(t => t.id === targetTask.id);
                            if (targetTaskIndex === -1)
                                return prev;
                            const insertAt = edge === "bottom" ? targetTaskIndex + 1 : targetTaskIndex;
                            // Insert task at new position with updated column
                            newTasks.splice(insertAt, 0, Object.assign(Object.assign({}, task), { column: toColumn }));
                            return newTasks;
                        });
                        if (fromColumn !== dropTargetData.column) {
                            // ToDo: Complete this
                            // await saveTask(task.id, {column: dropTargetData.column});
                        }
                        return;
                    }
                    // Drop on column background
                    if ((0,_data__WEBPACK_IMPORTED_MODULE_7__.isColumnData)(dropTargetData)) {
                        const toColumn = dropTargetData.column;
                        if (fromColumn === toColumn)
                            return;
                        setTasks((prev) => {
                            return prev.map(t => t.id === task.id
                                ? Object.assign(Object.assign({}, t), { column: toColumn }) : t);
                        });
                        // await saveTask(task.id, {column: toColumn}); // ToDo: Complete this
                    }
                });
            },
        }), (0,_atlaskit_pragmatic_drag_and_drop_auto_scroll_element__WEBPACK_IMPORTED_MODULE_4__.autoScrollForElements)({
            element: scrollable,
            canScroll: ({ source }) => (0,_data__WEBPACK_IMPORTED_MODULE_7__.isCardData)(source.data),
        }));
    }, []);
    return (react__WEBPACK_IMPORTED_MODULE_0___default().createElement("div", { ref: scrollableRef, className: "kanban-board" }, statuses.map((col) => (react__WEBPACK_IMPORTED_MODULE_0___default().createElement(_Column__WEBPACK_IMPORTED_MODULE_8__.Column, { key: col.id, column: col, tasks: taskGroups[col.label] || [] })))));
};


/***/ }),

/***/ "./views/Kanban/components/Card.tsx":
/*!******************************************!*\
  !*** ./views/Kanban/components/Card.tsx ***!
  \******************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   Card: () => (/* binding */ Card)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _atlaskit_pragmatic_drag_and_drop_element_adapter__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @atlaskit/pragmatic-drag-and-drop/element/adapter */ "./node_modules/@atlaskit/pragmatic-drag-and-drop/dist/esm/entry-point/element/adapter.js");
/* harmony import */ var _atlaskit_pragmatic_drag_and_drop_hitbox_closest_edge__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @atlaskit/pragmatic-drag-and-drop-hitbox/closest-edge */ "./node_modules/@atlaskit/pragmatic-drag-and-drop-hitbox/dist/esm/closest-edge.js");
/* harmony import */ var _atlaskit_pragmatic_drag_and_drop_combine__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @atlaskit/pragmatic-drag-and-drop/combine */ "./node_modules/@atlaskit/pragmatic-drag-and-drop/dist/esm/entry-point/combine.js");
/* harmony import */ var tiny_invariant__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! tiny-invariant */ "./node_modules/tiny-invariant/dist/esm/tiny-invariant.js");
/* harmony import */ var _data__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ../data */ "./views/Kanban/data.tsx");






const IDLE = { type: 'idle' };
const Card = ({ task }) => {
    const outerRef = (0,react__WEBPACK_IMPORTED_MODULE_0__.useRef)(null);
    const innerRef = (0,react__WEBPACK_IMPORTED_MODULE_0__.useRef)(null);
    const [state, setState] = (0,react__WEBPACK_IMPORTED_MODULE_0__.useState)(IDLE);
    (0,react__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {
        console.log('[Card state]', state);
    }, [state]);
    (0,react__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {
        const outer = outerRef.current;
        const inner = innerRef.current;
        (0,tiny_invariant__WEBPACK_IMPORTED_MODULE_4__["default"])(outer && inner);
        return (0,_atlaskit_pragmatic_drag_and_drop_combine__WEBPACK_IMPORTED_MODULE_3__.combine)(
        // ------------------------------
        // Make draggable
        // ------------------------------
        (0,_atlaskit_pragmatic_drag_and_drop_element_adapter__WEBPACK_IMPORTED_MODULE_1__.draggable)({
            element: inner,
            getInitialData: ({ element }) => (0,_data__WEBPACK_IMPORTED_MODULE_5__.getCardData)({
                task,
                fromColumn: task.column,
                rect: element.getBoundingClientRect(),
            }),
            onDragStart() {
                setState({ type: 'dragging' });
            },
            onDrop() {
                setState(IDLE);
            },
        }), 
        // ------------------------------
        // Make droppable (for reordering)
        // ------------------------------
        (0,_atlaskit_pragmatic_drag_and_drop_element_adapter__WEBPACK_IMPORTED_MODULE_1__.dropTargetForElements)({
            element: outer,
            getIsSticky: () => true,
            // canDrop({ source }) {
            //     return isCardData(source.data);
            // },
            getData: ({ element, input }) => {
                const data = (0,_data__WEBPACK_IMPORTED_MODULE_5__.getCardDropTargetData)({ task, column: task.column });
                return (0,_atlaskit_pragmatic_drag_and_drop_hitbox_closest_edge__WEBPACK_IMPORTED_MODULE_2__.attachClosestEdge)(data, { element, input, allowedEdges: ['top', 'bottom'] });
            },
            onDragEnter({ source, self }) {
                if (!(0,_data__WEBPACK_IMPORTED_MODULE_5__.isCardData)(source.data))
                    return;
                if (source.data.task.id === task.id)
                    return;
                const edge = (0,_atlaskit_pragmatic_drag_and_drop_hitbox_closest_edge__WEBPACK_IMPORTED_MODULE_2__.extractClosestEdge)(self.data);
                if (!edge)
                    return;
                setState({
                    type: 'over',
                    draggingRect: source.data.rect,
                    closestEdge: edge,
                });
            },
            onDragLeave({ source }) {
                if (!(0,_data__WEBPACK_IMPORTED_MODULE_5__.isCardData)(source.data))
                    return;
                if (source.data.task.id === task.id)
                    return;
                setState(IDLE);
            },
            onDrop() {
                setState(IDLE);
            },
        }));
    }, [task]);
    return (react__WEBPACK_IMPORTED_MODULE_0___default().createElement("div", { ref: outerRef, className: "kanban-card-wrapper" },
        " ",
        state.type === 'over' && state.closestEdge === 'top' && (react__WEBPACK_IMPORTED_MODULE_0___default().createElement("span", { className: "kanban-drop-indicator top" })),
        react__WEBPACK_IMPORTED_MODULE_0___default().createElement("div", { ref: innerRef, className: `kanban-card ${state.type !== 'idle' ? state.type : ''}` },
            react__WEBPACK_IMPORTED_MODULE_0___default().createElement("h3", null, task.title),
            react__WEBPACK_IMPORTED_MODULE_0___default().createElement("p", null, task.description)),
        state.type === 'over' && state.closestEdge === 'bottom' && (react__WEBPACK_IMPORTED_MODULE_0___default().createElement("span", { className: "kanban-drop-indicator bottom" }))));
};


/***/ }),

/***/ "./views/Kanban/components/Column.tsx":
/*!********************************************!*\
  !*** ./views/Kanban/components/Column.tsx ***!
  \********************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   Column: () => (/* binding */ Column)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var tiny_invariant__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! tiny-invariant */ "./node_modules/tiny-invariant/dist/esm/tiny-invariant.js");
/* harmony import */ var _atlaskit_pragmatic_drag_and_drop_combine__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @atlaskit/pragmatic-drag-and-drop/combine */ "./node_modules/@atlaskit/pragmatic-drag-and-drop/dist/esm/entry-point/combine.js");
/* harmony import */ var _atlaskit_pragmatic_drag_and_drop_element_adapter__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @atlaskit/pragmatic-drag-and-drop/element/adapter */ "./node_modules/@atlaskit/pragmatic-drag-and-drop/dist/esm/entry-point/element/adapter.js");
/* harmony import */ var _atlaskit_pragmatic_drag_and_drop_auto_scroll_element__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! @atlaskit/pragmatic-drag-and-drop-auto-scroll/element */ "./node_modules/@atlaskit/pragmatic-drag-and-drop-auto-scroll/dist/esm/entry-point/element.js");
/* harmony import */ var _data__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ../data */ "./views/Kanban/data.tsx");
/* harmony import */ var _Card__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! ./Card */ "./views/Kanban/components/Card.tsx");
var __awaiter = (undefined && undefined.__awaiter) || function (thisArg, _arguments, P, generator) {
    function adopt(value) { return value instanceof P ? value : new P(function (resolve) { resolve(value); }); }
    return new (P || (P = Promise))(function (resolve, reject) {
        function fulfilled(value) { try { step(generator.next(value)); } catch (e) { reject(e); } }
        function rejected(value) { try { step(generator["throw"](value)); } catch (e) { reject(e); } }
        function step(result) { result.done ? resolve(result.value) : adopt(result.value).then(fulfilled, rejected); }
        step((generator = generator.apply(thisArg, _arguments || [])).next());
    });
};







const IDLE = { type: "idle" };
const Column = ({ column, tasks }) => {
    const scrollableRef = (0,react__WEBPACK_IMPORTED_MODULE_0__.useRef)(null);
    const headerRef = (0,react__WEBPACK_IMPORTED_MODULE_0__.useRef)(null);
    const containerRef = (0,react__WEBPACK_IMPORTED_MODULE_0__.useRef)(null);
    const [state, setState] = (0,react__WEBPACK_IMPORTED_MODULE_0__.useState)(IDLE);
    // const {saveTask} = useTasks();
    (0,react__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {
        console.log("[Column state]", state);
    }, []);
    (0,react__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {
        const scrollable = scrollableRef.current;
        const header = headerRef.current;
        const container = containerRef.current;
        (0,tiny_invariant__WEBPACK_IMPORTED_MODULE_1__["default"])(scrollable && header && container);
        const columnData = (0,_data__WEBPACK_IMPORTED_MODULE_5__.getColumnData)({ column: column.label });
        return (0,_atlaskit_pragmatic_drag_and_drop_combine__WEBPACK_IMPORTED_MODULE_2__.combine)(
        // Make the column draggable (for future enhancement)
        (0,_atlaskit_pragmatic_drag_and_drop_element_adapter__WEBPACK_IMPORTED_MODULE_3__.draggable)({
            element: header,
            getInitialData: () => columnData,
            onDragStart() {
                setState({ type: "dragging" });
            },
            onDrop() {
                setState(IDLE);
            }
        }), 
        // Make column a valid drop target for cards.
        (0,_atlaskit_pragmatic_drag_and_drop_element_adapter__WEBPACK_IMPORTED_MODULE_3__.dropTargetForElements)({
            element: container,
            canDrop: ({ source }) => (0,_data__WEBPACK_IMPORTED_MODULE_5__.isCardData)(source.data),
            getData: () => columnData,
            onDragEnter({ source }) {
                if ((0,_data__WEBPACK_IMPORTED_MODULE_5__.isCardData)(source.data)) {
                    setState({ type: "drag-over-card", draggingRect: source.data.rect });
                }
            },
            onDropTargetChange({ source, location }) {
                if ((0,_data__WEBPACK_IMPORTED_MODULE_5__.isCardData)(source.data)) {
                    const hasNoTasks = tasks.length === 0;
                    if (hasNoTasks) {
                        setState({ type: "drag-over-empty" });
                    }
                    else {
                        setState({ type: "drag-over-card", draggingRect: source.data.rect });
                    }
                }
            },
            onDragLeave() {
                setState(IDLE);
            },
            onDrop(_a) {
                return __awaiter(this, arguments, void 0, function* ({ source }) {
                    if (!(0,_data__WEBPACK_IMPORTED_MODULE_5__.isCardData)(source.data))
                        return;
                    const { task, fromColumn } = source.data;
                    if (fromColumn !== column.label) {
                        // await saveTask(task.id, {column});
                    }
                    setState(IDLE);
                });
            },
        }), 
        // Auto-scroll while dragging cards.
        (0,_atlaskit_pragmatic_drag_and_drop_auto_scroll_element__WEBPACK_IMPORTED_MODULE_4__.autoScrollForElements)({
            element: scrollable,
            canScroll: ({ source }) => (0,_data__WEBPACK_IMPORTED_MODULE_5__.isCardData)(source.data),
        }));
    }, [column, tasks]);
    return (react__WEBPACK_IMPORTED_MODULE_0___default().createElement("div", { className: "kanban-column" },
        react__WEBPACK_IMPORTED_MODULE_0___default().createElement("div", { ref: headerRef, className: "kanban-column-header" },
            react__WEBPACK_IMPORTED_MODULE_0___default().createElement("h2", null, column.label)),
        react__WEBPACK_IMPORTED_MODULE_0___default().createElement("div", { ref: scrollableRef, className: "kanban-column-scrollable" },
            react__WEBPACK_IMPORTED_MODULE_0___default().createElement("div", { ref: containerRef, className: "kanban-column-container" },
                tasks.map((task) => (react__WEBPACK_IMPORTED_MODULE_0___default().createElement(_Card__WEBPACK_IMPORTED_MODULE_6__.Card, { key: task.id, task: task }))),
                state.type === "drag-over-empty" && (react__WEBPACK_IMPORTED_MODULE_0___default().createElement("div", { className: "kanban-drop-indicator" }))))));
};


/***/ }),

/***/ "./views/Kanban/data.tsx":
/*!*******************************!*\
  !*** ./views/Kanban/data.tsx ***!
  \*******************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   getCardData: () => (/* binding */ getCardData),
/* harmony export */   getCardDropTargetData: () => (/* binding */ getCardDropTargetData),
/* harmony export */   getColumnData: () => (/* binding */ getColumnData),
/* harmony export */   getColumnDropTargetData: () => (/* binding */ getColumnDropTargetData),
/* harmony export */   isCardData: () => (/* binding */ isCardData),
/* harmony export */   isCardDropTargetData: () => (/* binding */ isCardDropTargetData),
/* harmony export */   isColumnData: () => (/* binding */ isColumnData),
/* harmony export */   isColumnDropTargetData: () => (/* binding */ isColumnDropTargetData)
/* harmony export */ });
// ------------------------------
// Type Guards
// ------------------------------
function isCardData(data) {
    return (data === null || data === void 0 ? void 0 : data.type) === "card" && !!data.task;
}
function isColumnData(data) {
    return (data === null || data === void 0 ? void 0 : data.type) === "column" && typeof data.column === "string";
}
function isCardDropTargetData(data) {
    return (data === null || data === void 0 ? void 0 : data.type) === "card-drop-target" && !!data.task;
}
function isColumnDropTargetData(data) {
    return (data === null || data === void 0 ? void 0 : data.type) === "column-drop-target" && typeof data.column === "string";
}
// ------------------------------
// Card helpers
// ------------------------------
function getCardData({ task, fromColumn, rect, }) {
    return {
        type: "card",
        task,
        fromColumn,
        rect,
    };
}
function getCardDropTargetData({ task, column }) {
    return {
        type: "card-drop-target",
        task,
        column,
    };
}
// ------------------------------
// Column helpers
// ------------------------------
function getColumnData({ column }) {
    return {
        type: "column",
        column,
    };
}
function getColumnDropTargetData({ column }) {
    return {
        type: "column-drop-target",
        column,
    };
}


/***/ }),

/***/ "react":
/*!************************!*\
  !*** external "React" ***!
  \************************/
/***/ ((module) => {

module.exports = React;

/***/ }),

/***/ "react-dom":
/*!***************************!*\
  !*** external "ReactDOM" ***!
  \***************************/
/***/ ((module) => {

module.exports = ReactDOM;

/***/ })

/******/ 	});
/************************************************************************/
/******/ 	// The module cache
/******/ 	var __webpack_module_cache__ = {};
/******/ 	
/******/ 	// The require function
/******/ 	function __webpack_require__(moduleId) {
/******/ 		// Check if module is in cache
/******/ 		var cachedModule = __webpack_module_cache__[moduleId];
/******/ 		if (cachedModule !== undefined) {
/******/ 			return cachedModule.exports;
/******/ 		}
/******/ 		// Create a new module (and put it into the cache)
/******/ 		var module = __webpack_module_cache__[moduleId] = {
/******/ 			// no module.id needed
/******/ 			// no module.loaded needed
/******/ 			exports: {}
/******/ 		};
/******/ 	
/******/ 		// Execute the module function
/******/ 		__webpack_modules__[moduleId].call(module.exports, module, module.exports, __webpack_require__);
/******/ 	
/******/ 		// Return the exports of the module
/******/ 		return module.exports;
/******/ 	}
/******/ 	
/************************************************************************/
/******/ 	/* webpack/runtime/compat get default export */
/******/ 	(() => {
/******/ 		// getDefaultExport function for compatibility with non-harmony modules
/******/ 		__webpack_require__.n = (module) => {
/******/ 			var getter = module && module.__esModule ?
/******/ 				() => (module['default']) :
/******/ 				() => (module);
/******/ 			__webpack_require__.d(getter, { a: getter });
/******/ 			return getter;
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/define property getters */
/******/ 	(() => {
/******/ 		// define getter functions for harmony exports
/******/ 		__webpack_require__.d = (exports, definition) => {
/******/ 			for(var key in definition) {
/******/ 				if(__webpack_require__.o(definition, key) && !__webpack_require__.o(exports, key)) {
/******/ 					Object.defineProperty(exports, key, { enumerable: true, get: definition[key] });
/******/ 				}
/******/ 			}
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/hasOwnProperty shorthand */
/******/ 	(() => {
/******/ 		__webpack_require__.o = (obj, prop) => (Object.prototype.hasOwnProperty.call(obj, prop))
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/make namespace object */
/******/ 	(() => {
/******/ 		// define __esModule on exports
/******/ 		__webpack_require__.r = (exports) => {
/******/ 			if(typeof Symbol !== 'undefined' && Symbol.toStringTag) {
/******/ 				Object.defineProperty(exports, Symbol.toStringTag, { value: 'Module' });
/******/ 			}
/******/ 			Object.defineProperty(exports, '__esModule', { value: true });
/******/ 		};
/******/ 	})();
/******/ 	
/************************************************************************/
var __webpack_exports__ = {};
// This entry needs to be wrapped in an IIFE because it needs to be isolated against other modules in the chunk.
(() => {
/*!*******************!*\
  !*** ./index.tsx ***!
  \*******************/
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var react_dom_client__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! react-dom/client */ "./node_modules/react-dom/client.js");
/* harmony import */ var _context_ViewContext__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./context/ViewContext */ "./context/ViewContext.tsx");
/* harmony import */ var _context_TaskContext__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ./context/TaskContext */ "./context/TaskContext.tsx");
/* harmony import */ var _components_Page__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ./components/Page */ "./components/Page.tsx");





const container = document.getElementById('wpo-aom-task-manager-container');
if (container) {
    const root = (0,react_dom_client__WEBPACK_IMPORTED_MODULE_1__.createRoot)(container);
    root.render(react__WEBPACK_IMPORTED_MODULE_0___default().createElement((react__WEBPACK_IMPORTED_MODULE_0___default().StrictMode), null,
        react__WEBPACK_IMPORTED_MODULE_0___default().createElement(_context_TaskContext__WEBPACK_IMPORTED_MODULE_3__.TaskProvider, null,
            react__WEBPACK_IMPORTED_MODULE_0___default().createElement(_context_ViewContext__WEBPACK_IMPORTED_MODULE_2__.ViewProvider, null,
                react__WEBPACK_IMPORTED_MODULE_0___default().createElement(_components_Page__WEBPACK_IMPORTED_MODULE_4__["default"], null)))));
}

})();

/******/ })()
;
//# sourceMappingURL=task-manager.js.map