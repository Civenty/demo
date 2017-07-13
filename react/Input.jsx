import React from 'react';
import InputElement from 'react-input-mask';
import Loader from './loader';
import CancelEditBtn from './CancelEditBtn';
import SubmitBtn from './SubmitBtn';
import InterectionItem from './InterectionItem';
import './input.css';

class Input extends React.Component {
  constructor() {
    super();

    this.onChange = this.onChange.bind(this);
    this.onSubmit = this.onSubmit.bind(this);
    this.sendMessage = this.sendMessage.bind(this);
    this.cancelEditing = this.cancelEditing.bind(this);
    this.connectDOMInput = this.connectDOMInput.bind(this);

    this.onFocus = this.onFocus.bind(this);
    this.onBlur = this.onBlur.bind(this);

  }
  componentDidMount() {
    if (!this.props.isFetching && !this.props.isMobileDevice) {
      this.focus();
      setTimeout(() => {
        if (document.activeElement !== this.DOMInput) {
          this.focus();
        }
      }, 50);
    }
  }
  componentDidUpdate({isFetching, fieldName}) {
    if (fieldName !== this.props.fieldName && !this.props.isMobileDevice) {
      this.focus();
    } else if (this.props.isFetching && this.props.isMobileDevice) {
      this.blur();
    }
  }
  onChange(event) {
    const value = event.target.value;

    /**
     * Лечит хак с исчезающей маской
     */
    if (this.props.mask && (value === '' || value === ' ')) {
      return;
    }

    this.props.onChange(value);
  }
  onSubmit() {

    if (this.props.isMobileDevice) {
      this.blur();
      this.notifyParentWindowBlur();
    }

    this.props.onSubmit(String(this.props.value).trim());
  }
  notifyParentWindowFocus() {
    window.parent && window.parent.postMessage('FIX_OUTER_BODY_FOCUS', '*');
  }
  notifyParentWindowBlur() {
    window.parent && window.parent.postMessage('FIX_OUTER_BODY_BLUR', '*');
  }
  focus() {
    this.DOMInput.focus();
  }
  blur() {
    this.DOMInput.blur();
  }
  cancelEditing() {
    this.props.onCancel(this.props.savedValue);
  }
  onBlur() {
    this.notifyParentWindowBlur();
    if (this.props.focused) {
      this.props.onBlur();
    }
  }
  onFocus() {
    this.notifyParentWindowFocus();
    if (!this.props.focused) {
      this.props.onFocus();
    }
  }
  connectDOMInput(input) {
    this.DOMInput = (input && input.refs.input) || {};
  }
  shouldApplyDefaultValue() {
    if (this.props.isMobileDevice) {
      return this.props.mask;
    }
    return this.props.mask && this.props.focused && this.props.placeholder;
  }
  sendMessage(e) {
    if (e.keyCode === 13) {
      this.onSubmit();
    }
  }
  render() {
    const activeClassName = this.props.focused ? 'user-interaction-wrapper_active' : '';
    const defaultValue = this.shouldApplyDefaultValue() ? ' ' : '';

    return (
      <div className={`user-interaction-wrapper ${activeClassName}`}>
        <div className="user-interaction">

          <InterectionItem>
            <InputElement
              type={this.props.inputType}
              ref={this.connectDOMInput}
              mask={this.props.mask}
              disabled={this.props.isFetching}
              value={this.props.value || defaultValue}
              onChange={this.onChange}
              alwaysShowMask={false}
              placeholder={this.props.placeholder}
              onKeyUp={this.sendMessage}
              onFocus={this.onFocus}
              onBlur={this.onBlur} />
          </ InterectionItem>

          {this.props.isFetching && <InterectionItem cls={'user-interaction__cell_indicator user-interaction__cell_indicator_loading'}>
            <Loader />
          </ InterectionItem>}

          {this.props.editMode && !this.props.isFetching && <InterectionItem cls={'user-interaction__cell_btn'}>
            <CancelEditBtn onClick={this.cancelEditing} />
          </ InterectionItem>}

          <InterectionItem cls={'user-interaction__cell_btn'}>
            <SubmitBtn onSubmit={this.onSubmit} isFetching={this.props.isFetching} />
          </ InterectionItem>
        </div>
      </div>
    );
  }
}

Input.defaultProps = {
  isFetching: false,
  editMode: false,
  isMobileDevice: false,
  mask: ''
};

Input.propTypes = {
  fieldName: React.PropTypes.string,
  value: React.PropTypes.string,
  mask: React.PropTypes.string,
  placeholder: React.PropTypes.string,
  inputType: React.PropTypes.string,
  savedValue: React.PropTypes.string,
  isMobileDevice: React.PropTypes.bool,
  isFetching: React.PropTypes.bool,
  editMode: React.PropTypes.bool,
  focused: React.PropTypes.bool,
  onBlur: React.PropTypes.func,
  onFocus: React.PropTypes.func,
  onChange: React.PropTypes.func,
  onSubmit: React.PropTypes.func,
  onCancel: React.PropTypes.func
};

export default Input;
