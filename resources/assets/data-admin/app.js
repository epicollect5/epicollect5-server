import React from 'react';
import ReactDOM from 'react-dom';
import { Provider } from 'react-redux';
import { createStore, applyMiddleware } from 'redux';
import reducers from 'reducers';
import promiseMiddleware from 'redux-promise-middleware';
import thunk from 'redux-thunk';
import { createLogger } from 'redux-logger';

import DecimalAdjust from 'utils/DecimalAdjust';

import Main from 'components/Main';//todo make all the module import from root


const createStoreWithMiddleware = applyMiddleware(
    promiseMiddleware(),
    thunk,
    createLogger()
)(createStore);

ReactDOM.render(
    <Provider store={createStoreWithMiddleware(reducers)}>
        <Main />
    </Provider>
    , document.getElementById('app'));

